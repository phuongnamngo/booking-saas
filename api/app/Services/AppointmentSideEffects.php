<?php

namespace App\Services;

use App\Jobs\NotifyWaitlist;
use App\Jobs\RefundPayment;
use App\Mail\AppointmentCancelledMail;
use App\Mail\AppointmentConfirmedMail;
use App\Mail\AppointmentRescheduledMail;
use App\Mail\ReviewRequestMail;
use App\Models\Appointment;
use App\Models\Review;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\AppointmentActivity;
use App\Support\TenantContext;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

class AppointmentSideEffects
{
    public function created(Appointment $appointment, string $manageToken): void
    {
        $this->queue($appointment, new AppointmentConfirmedMail($appointment, $manageToken));
        $this->notify($appointment, 'Lịch mới');
    }

    public function rescheduled(Appointment $appointment, ?string $openedAt = null, ?string $manageToken = null): void
    {
        $this->queue($appointment, new AppointmentRescheduledMail($appointment, $manageToken ?? ''));
        $this->notify($appointment, 'Đổi giờ');
        $this->openWaitlist($appointment, $openedAt ?? $appointment->starts_at->toIso8601String());
    }

    public function cancelled(Appointment $appointment): void
    {
        $this->queue($appointment, new AppointmentCancelledMail($appointment));
        $this->notify($appointment, 'Hủy lịch');
        $this->refundSucceeded($appointment);
        $this->openWaitlist($appointment, $appointment->starts_at->toIso8601String());
    }

    private function refundSucceeded(Appointment $appointment): void
    {
        $payment = $appointment->payments()->where('status', 'succeeded')->latest('id')->first();

        if ($payment === null || $payment->refunds()->where('status', 'succeeded')->exists()) {
            return;
        }

        RefundPayment::dispatch((int) $payment->id, (int) $payment->tenant_id);
    }

    private function openWaitlist(Appointment $appointment, string $startsAt): void
    {
        $serviceIds = $appointment->services()->with('service')->get()
            ->map(fn ($row): ?string => $row->service?->public_id)
            ->filter()
            ->values()
            ->all();

        NotifyWaitlist::dispatch(
            (int) $appointment->tenant_id,
            (int) $appointment->branch_id,
            (int) $appointment->staff_id,
            $serviceIds,
            $startsAt,
        );
    }

    public function completed(Appointment $appointment): void
    {
        $appointment->loadMissing('customer');
        $email = trim((string) ($appointment->customer?->email ?? ''));

        if ($email === '' || Review::query()->where('appointment_id', $appointment->id)->exists()) {
            return;
        }

        $token = Str::random(40);
        Review::query()->create([
            'appointment_id' => $appointment->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(14),
        ]);
        Mail::to($email)->queue(new ReviewRequestMail($appointment, $token));
    }

    private function queue(Appointment $appointment, Mailable $mail): void
    {
        $appointment->loadMissing(['customer', 'staff.user']);
        $addresses = [];
        $customerEmail = $appointment->customer?->email;

        if (is_string($customerEmail) && $customerEmail !== '') {
            $addresses[] = $customerEmail;
        }

        $staffEmail = $appointment->staff?->user?->email;

        if (is_string($staffEmail) && $staffEmail !== '') {
            $addresses[] = $staffEmail;
        }

        $addresses = array_values(array_unique($addresses));

        if ($addresses === []) {
            return;
        }

        Mail::to($addresses)->queue($mail);
    }

    private function notify(Appointment $appointment, string $message): void
    {
        $tenant = Tenant::query()->find($appointment->tenant_id);

        if ($tenant !== null) {
            app(TenantContext::class)->set($tenant);
            setPermissionsTeamId($tenant->id);
        }

        $appointment->loadMissing(['staff.user']);
        $recipients = $this->usersInRole('owner')->merge(
            $this->usersInRole('manager', $appointment->branch_id),
        );
        $staffUser = $appointment->staff?->user;

        if ($staffUser !== null) {
            $recipients->push($staffUser);
        }

        $recipients->unique('id')->each(function (User $user) use ($message): void {
            $user->notify(new AppointmentActivity($message));
        });
    }

    /**
     * @return Collection<int, User>
     */
    private function usersInRole(string $role, ?int $branchId = null): Collection
    {
        try {
            $query = User::query()->role($role);

            if ($branchId !== null) {
                $query->whereHas('branches', fn ($branches) => $branches->where('branches.id', $branchId));
            }

            return $query->get();
        } catch (RoleDoesNotExist) {
            return collect();
        }
    }
}
