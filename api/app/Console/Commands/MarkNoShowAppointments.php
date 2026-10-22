<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\Tenant;
use App\Services\AuditLogger;
use App\Services\AvailabilityCache;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MarkNoShowAppointments extends Command
{
    protected $signature = 'appointments:mark-no-shows';

    protected $description = 'Đánh dấu lịch confirmed quá 15 phút là không đến';

    public function handle(): int
    {
        $cutoff = CarbonImmutable::now('UTC')->subMinutes(15);
        $ids = Appointment::withoutGlobalScope('tenant')
            ->where('status', 'confirmed')
            ->where('starts_at', '<=', $cutoff)
            ->pluck('id');

        foreach ($ids as $id) {
            DB::transaction(function () use ($id): void {
                $appointment = Appointment::withoutGlobalScope('tenant')->whereKey($id)->lockForUpdate()->first();

                if ($appointment === null || $appointment->status !== 'confirmed') {
                    return;
                }

                $tenant = Tenant::query()->find($appointment->tenant_id);

                if ($tenant === null) {
                    return;
                }

                app(TenantContext::class)->set($tenant);
                $appointment->update(['status' => 'no_show']);
                $logger = app(AuditLogger::class);
                $logger->write(
                    null,
                    'appointment.no_show',
                    $appointment,
                    ['status' => 'confirmed'],
                    $logger->appointmentState($appointment),
                );
                app(AvailabilityCache::class)->forgetAppointment($appointment);
            });
        }

        return self::SUCCESS;
    }
}
