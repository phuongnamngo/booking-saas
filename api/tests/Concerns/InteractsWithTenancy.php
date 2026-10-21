<?php

namespace Tests\Concerns;

use App\Models\Tenant;
use App\Support\TenantContext;

trait InteractsWithTenancy
{
    protected function setTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant);
        setPermissionsTeamId($tenant->id);
    }
}
