<?php

namespace App\Services;

use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentBooker
{
    public function __construct(private readonly AvailabilityService $availability) {}

    /**
     * @param  Collection<int, Service>  $services
     * @param  array{name: string, phone: string, email?: string|null}  $customer
     */
    public function create(
        Branch $branch,
        StaffProfile $staff,
        Collection $services,
        CarbonImmutable $startsAt,
        array $customer,
        ?User $actor,
    ): Appointment {
        if ($startsAt->timezone($branch->timezone)->lessThan(CarbonImmutable::now($branch->timezone))) {
            throw ValidationException::withMessages([
                'starts_at' => 'Không đặt lịch trong quá khứ.',
            ]);
        }

        if ($services->contains(fn ($service): bool => ! $service->is_active)) {
            throw ValidationException::withMessages([
                'service_ids' => 'Dịch vụ không còn nhận đặt.',
            ]);
        }

        return DB::transaction(function () use ($branch, $staff, $services, $startsAt, $customer, $actor): Appointment {
            $locked = StaffProfile::query()->whereKey($staff->id)->lockForUpdate()->first();

            if ($locked === null || ! $this->slotIsOpen($branch, $locked, $services, $startsAt, null)) {
                throw new SlotUnavailableException;
            }

            $phone = trim($customer['phone']);
            $record = Customer::query()->where('phone', $phone)->first();

            if ($record === null) {
                $record = Customer::query()->create([
                    'name' => $customer['name'],
                    'phone' => $phone,
                    'email' => $customer['email'] ?? null,
                ]);
            } else {
                $record->update([
                    'name' => $customer['name'],
                    'email' => $customer['email'] ?? $record->email,
                ]);
            }

            $appointment = Appointment::query()->create([
                'branch_id' => $branch->id,
                'staff_id' => $locked->id,
                'customer_id' => $record->id,
                'created_by' => $actor?->id,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->addMinutes((int) $services->sum('duration_minutes')),
                'status' => 'pending',
            ]);

            foreach ($services->values() as $position => $service) {
                $appointment->services()->create([
                    'service_id' => $service->id,
                    'position' => $position,
                    'duration_minutes' => $service->duration_minutes,
                    'price_amount' => $service->price_amount,
                ]);
            }

            return $appointment;
        });
    }

    /**
     * @return list<int>
     */
    public function staffIdsForUpdate(int $current, int $next): array
    {
        return collect([$current, $next])->unique()->sort()->values()->all();
    }

    /**
     * @param  Collection<int, Service>|null  $services
     */
    public function reschedule(
        Appointment $appointment,
        CarbonImmutable $startsAt,
        ?StaffProfile $staff = null,
        ?Collection $services = null,
    ): Appointment {
        if (! in_array($appointment->status, ['pending', 'confirmed'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Không đổi được lịch ở trạng thái này.',
            ]);
        }

        $staff ??= $appointment->staff;
        $services ??= Service::query()
            ->whereIn('id', $appointment->services()->orderBy('position')->pluck('service_id'))
            ->get();

        if ($startsAt->timezone($appointment->branch->timezone)->lessThan(CarbonImmutable::now($appointment->branch->timezone))) {
            throw ValidationException::withMessages([
                'starts_at' => 'Không đặt lịch trong quá khứ.',
            ]);
        }

        return DB::transaction(function () use ($appointment, $startsAt, $staff, $services): Appointment {
            foreach ($this->staffIdsForUpdate($appointment->staff_id, $staff->id) as $staffId) {
                StaffProfile::query()->whereKey($staffId)->lockForUpdate()->first();
            }

            if (! $this->slotIsOpen($appointment->branch, $staff, $services, $startsAt, $appointment->id)) {
                throw new SlotUnavailableException;
            }

            $appointment->update([
                'staff_id' => $staff->id,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->addMinutes((int) $services->sum('duration_minutes')),
            ]);

            $appointment->services()->delete();
            foreach ($services->values() as $position => $service) {
                $appointment->services()->create([
                    'service_id' => $service->id,
                    'position' => $position,
                    'duration_minutes' => $service->duration_minutes,
                    'price_amount' => $service->price_amount,
                ]);
            }

            return $appointment->refresh();
        });
    }

    /**
     * @param  Collection<int, Service>  $services
     */
    private function slotIsOpen(Branch $branch, StaffProfile $staff, Collection $services, CarbonImmutable $startsAt, ?int $ignoreAppointmentId): bool
    {
        $date = $startsAt->timezone($branch->timezone)->toDateString();

        foreach ($this->availability->slots($branch, $staff, $services, $date, $ignoreAppointmentId) as $slot) {
            if ($slot['starts_at']->equalTo($startsAt->utc())) {
                return true;
            }
        }

        return false;
    }
}
