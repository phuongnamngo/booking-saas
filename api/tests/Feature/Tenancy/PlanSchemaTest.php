<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PlanSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_tenant_defaults_to_free_and_flags_are_empty(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);

        $this->assertSame('free', $tenant->fresh()->plan);
        $this->assertNull($tenant->fresh()->suspended_at);
        $this->assertFalse($user->fresh()->is_super_admin);
        $this->assertTrue(Schema::hasColumn('appointments', 'manage_token_hash'));
    }
}
