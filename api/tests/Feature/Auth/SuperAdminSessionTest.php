<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperAdminSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_session_has_no_tenant_role(): void
    {
        app(TenantContext::class)->forget();
        User::withoutGlobalScope('tenant')->create([
            'name' => 'Root',
            'email' => 'root@example.com',
            'password' => 'password',
            'tenant_id' => null,
            'is_super_admin' => true,
            'email_verified_at' => now(),
        ]);

        $this->withHeader('Referer', 'http://localhost:5173')->get('/sanctum/csrf-cookie');
        $this->withHeaders([
            'Referer' => 'http://localhost:5173',
            'X-XSRF-TOKEN' => csrf_token(),
        ])->postJson('/api/v1/auth/login', [
            'email' => 'root@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('tenant', null)
            ->assertJsonPath('is_super_admin', true)
            ->assertJsonPath('role', null)
            ->assertJsonPath('permissions', []);
    }
}
