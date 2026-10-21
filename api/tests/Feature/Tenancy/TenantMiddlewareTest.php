<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_slug_is_404_and_null_tenant_user_is_403(): void
    {
        $this->getJson('/api/v1/public/missing/services')->assertNotFound();

        $user = User::factory()->create(['tenant_id' => null]);

        $this->actingAs($user)->getJson('/api/v1/branches')->assertForbidden();
    }
}
