<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Services\AvailabilityService;
use App\Support\TenantContext;
use Database\Seeders\DemoTenantSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoTenantSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_tenant_has_open_slots_tomorrow(): void
    {
        $this->seed(DemoTenantSeeder::class);

        $tenant = Tenant::query()->where('slug', 'demo')->firstOrFail();
        app(TenantContext::class)->set($tenant);

        $branch = Branch::query()->firstOrFail();
        $staff = StaffProfile::query()->firstOrFail();
        $service = Service::query()->where('is_active', true)->firstOrFail();
        $tomorrow = now()->timezone($branch->timezone)->addDay()->toDateString();

        $slots = app(AvailabilityService::class)->slots(
            $branch,
            $staff,
            collect([$service]),
            $tomorrow,
        );

        $this->assertNotEmpty($slots);
    }
}
