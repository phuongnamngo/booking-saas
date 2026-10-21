<?php

namespace App\Jobs;

use App\Mail\WaitlistOpenedMail;
use App\Models\Branch;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\WaitlistEntry;
use App\Services\AvailabilityService;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class NotifyWaitlist implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $servicePublicIds
     */
    public function __construct(
        public int $tenantId,
        public int $branchId,
        public int $staffId,
        public array $servicePublicIds,
        public string $startsAt,
    ) {}

    public function handle(AvailabilityService $availability): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->set($tenant);
        $branch = Branch::query()->find($this->branchId);
        $staff = StaffProfile::query()->find($this->staffId);

        if ($branch === null || $staff === null) {
            return;
        }

        $startsAt = CarbonImmutable::parse($this->startsAt)->utc();
        $services = Service::query()->whereIn('public_id', $this->servicePublicIds)->get();
        $date = $startsAt->timezone($branch->timezone)->toDateString();
        $open = false;

        foreach ($availability->slots($branch, $staff, $services, $date) as $slot) {
            if ($slot['starts_at']->equalTo($startsAt)) {
                $open = true;
                break;
            }
        }

        if (! $open) {
            return;
        }

        $entry = WaitlistEntry::query()
            ->where('branch_id', $branch->id)
            ->where('staff_id', $staff->id)
            ->whereNull('notified_at')
            ->orderBy('id')
            ->get()
            ->first(fn (WaitlistEntry $row): bool => $row->starts_at?->utc()->equalTo($startsAt) === true);

        if ($entry === null || $entry->customer_email === '') {
            return;
        }

        Mail::to($entry->customer_email)->queue(new WaitlistOpenedMail($entry));
        $entry->update(['notified_at' => now()]);
    }
}
