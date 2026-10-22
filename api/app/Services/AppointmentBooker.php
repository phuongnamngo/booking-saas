<?php

namespace App\Services;

use App\Exceptions\SlotUnavailableException;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Support\ManageToken;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
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
        ?string $idempotencyKey = null,
        ?string $idempotencyHash = null,
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

        return DB::transaction(function () use ($branch, $staff, $services, $startsAt, $customer, $actor, $idempotencyKey, $idempotencyHash): Appointment {
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

            if (is_string($idempotencyKey) && $idempotencyKey !== '' && is_string($idempotencyHash)) {
                $appointment->loadMissing('staff');
                app(IdempotencyStore::class)->store(
                    (int) $appointment->tenant_id,
                    $idempotencyKey,
                    $idempotencyHash,
                    [
                        'id' => $appointment->public_id,
                        'staff_id' => $appointment->staff?->public_id,
                        'status' => $appointment->status,
                        'starts_at' => $appointment->starts_at->toIso8601String(),
                        'ends_at' => $appointment->ends_at->toIso8601String(),
                    ],
                );
            }

            $logger = app(AuditLogger::class);
            $logger->write($actor, 'appointment.created', $appointment, null, $logger->appointmentState($appointment));
            $token = ManageToken::issue($appointment);

            DB::afterCommit(function () use ($appointment, $token): void {
                app(AvailabilityCache::class)->forgetAppointment($appointment);
                app(AppointmentSideEffects::class)->created($appointment, $token);
            });

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
        ?User $actor = null,
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

        return DB::transaction(function () use ($appointment, $startsAt, $staff, $services, $actor): Appointment {
            foreach ($this->staffIdsForUpdate($appointment->staff_id, $staff->id) as $staffId) {
                StaffProfile::query()->whereKey($staffId)->lockForUpdate()->first();
            }

            if (! $this->slotIsOpen($appointment->branch, $staff, $services, $startsAt, $appointment->id)) {
                throw new SlotUnavailableException;
            }

            $appointment->loadMissing(['branch', 'staff', 'services.service']);
            $logger = app(AuditLogger::class);
            $before = $logger->appointmentState($appointment);
            $previousKey = AvailabilityCache::key(
                (int) $appointment->tenant_id,
                $appointment->branch->public_id,
                $appointment->staff->public_id,
                $appointment->services->map(fn ($row): ?string => $row->service?->public_id)->filter()->values()->all(),
                $appointment->starts_at->timezone($appointment->branch->timezone)->toDateString(),
            );

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

            $updated = $appointment->refresh();
            $logger->write($actor, 'appointment.rescheduled', $updated, $before, $logger->appointmentState($updated));
            $token = ManageToken::issue($updated);
            DB::afterCommit(function () use ($previousKey, $updated, $before, $token): void {
                Cache::forget($previousKey);
                app(AvailabilityCache::class)->forgetAppointment($updated);
                app(AppointmentSideEffects::class)->rescheduled($updated, $before['starts_at'], $token);
            });

            return $updated;
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
