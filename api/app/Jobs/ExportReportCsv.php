<?php

namespace App\Jobs;

use App\Models\Branch;
use App\Models\ReportExport;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ReportBuilder;
use App\Support\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class ExportReportCsv implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $exportId, public int $tenantId) {}

    public function handle(ReportBuilder $reports): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        app(TenantContext::class)->set($tenant);
        setPermissionsTeamId($tenant->id);
        $export = ReportExport::query()->find($this->exportId);

        if ($export === null) {
            return;
        }

        $user = User::query()->find($export->user_id);
        $branches = $this->branches($export, $user);
        $from = $export->from_date->toDateString();
        $to = $export->to_date->toDateString();
        $report = $reports->build($branches, $from, $to, $export->group);

        $relative = 'reports/'.$export->tenant_id.'/'.$export->public_id.'.csv';
        Storage::disk('local')->makeDirectory('reports/'.$export->tenant_id);
        $handle = fopen(Storage::disk('local')->path($relative), 'w');
        if ($handle === false) {
            $export->update(['status' => 'failed']);

            return;
        }

        fputcsv($handle, ['period', 'revenue', 'cancel_rate', 'no_show_rate']);
        foreach ($report['by_period'] as $row) {
            fputcsv($handle, [
                $row['period'],
                $row['revenue'],
                $report['cancel_rate'],
                $report['no_show_rate'],
            ]);
        }
        fclose($handle);

        $export->update([
            'status' => 'ready',
            'path' => $relative,
        ]);
    }

    /**
     * @return Collection<int, Branch>
     */
    private function branches(ReportExport $export, ?User $user): Collection
    {
        if ($export->branch_id !== null) {
            return Branch::query()->whereKey($export->branch_id)->get();
        }

        if ($user !== null && $user->hasRole('owner')) {
            return Branch::query()->get();
        }

        if ($user !== null) {
            return $user->branches()->get();
        }

        return collect();
    }
}
