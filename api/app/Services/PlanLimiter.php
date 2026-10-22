<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Support\TenantContext;
use Illuminate\Validation\ValidationException;

class PlanLimiter
{
    public function assertCanCreate(string $kind): void
    {
        $tenant = Tenant::query()->whereKey(app(TenantContext::class)->id())->lockForUpdate()->firstOrFail();
        $cap = (int) config('plans.'.$tenant->plan.'.'.$kind);
        $count = $kind === 'branches'
            ? Branch::query()->count()
            : StaffProfile::query()->count();

        if ($count >= $cap) {
            $label = $kind === 'branches' ? 'chi nhánh' : 'nhân viên';

            throw ValidationException::withMessages([
                'plan' => ['Gói '.ucfirst((string) $tenant->plan).' chỉ có '.$cap.' '.$label.'.'],
            ]);
        }
    }
}
