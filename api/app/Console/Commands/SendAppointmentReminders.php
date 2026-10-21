<?php

namespace App\Console\Commands;

use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Models\Tenant;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class SendAppointmentReminders extends Command
{
    protected $signature = 'appointments:remind';

    protected $description = 'Gửi nhắc lịch trong cửa sổ 24 giờ và 2 giờ';

    public function handle(): int
    {
        $now = CarbonImmutable::now('UTC');
        $this->send(
            'reminder_24h_sent_at',
            $now->addHours(23)->addMinutes(45),
            $now->addHours(24)->addMinutes(15),
        );
        $this->send(
            'reminder_2h_sent_at',
            $now->addHours(1)->addMinutes(45),
            $now->addHours(2)->addMinutes(15),
        );

        return self::SUCCESS;
    }

    private function send(string $column, CarbonImmutable $after, CarbonImmutable $until): void
    {
        $ids = Appointment::withoutGlobalScope('tenant')
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereNull($column)
            ->where('starts_at', '>', $after->format('Y-m-d H:i:s'))
            ->where('starts_at', '<=', $until->format('Y-m-d H:i:s'))
            ->pluck('id');

        foreach ($ids as $id) {
            DB::transaction(function () use ($id, $column): void {
                $appointment = Appointment::withoutGlobalScope('tenant')->whereKey($id)->lockForUpdate()->first();

                if ($appointment === null || $appointment->{$column} !== null) {
                    return;
                }

                if (! in_array($appointment->status, ['pending', 'confirmed'], true)) {
                    return;
                }

                $appointment->update([$column => CarbonImmutable::now('UTC')]);
                $this->queue($appointment);
            });
        }
    }

    private function queue(Appointment $appointment): void
    {
        $tenant = Tenant::query()->find($appointment->tenant_id);

        if ($tenant !== null) {
            app(TenantContext::class)->set($tenant);
        }

        $appointment->load(['customer', 'staff.user']);
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

        Mail::to($addresses)->queue(new AppointmentReminderMail($appointment));
    }
}
