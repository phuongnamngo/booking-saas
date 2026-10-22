<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_command_stops_when_env_is_missing(): void
    {
        putenv('SUPER_ADMIN_EMAIL');
        putenv('SUPER_ADMIN_PASSWORD');
        unset($_ENV['SUPER_ADMIN_EMAIL'], $_ENV['SUPER_ADMIN_PASSWORD'], $_SERVER['SUPER_ADMIN_EMAIL'], $_SERVER['SUPER_ADMIN_PASSWORD']);

        $this->artisan('admin:create')->assertFailed();
        $this->assertSame(0, User::withoutGlobalScope('tenant')->count());
    }

    public function test_command_creates_a_verified_super_admin_without_a_tenant(): void
    {
        putenv('SUPER_ADMIN_EMAIL=root@example.com');
        putenv('SUPER_ADMIN_PASSWORD=password');
        $_ENV['SUPER_ADMIN_EMAIL'] = 'root@example.com';
        $_ENV['SUPER_ADMIN_PASSWORD'] = 'password';

        $this->artisan('admin:create')->assertSuccessful();

        $user = User::withoutGlobalScope('tenant')->where('email', 'root@example.com')->firstOrFail();
        $this->assertNull($user->tenant_id);
        $this->assertTrue($user->is_super_admin);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_owner_cannot_manage_tenants(): void
    {
        $this->registerAndLogin('owner@example.com', 'spa-a');

        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/admin/tenants')
            ->assertForbidden();
    }

    public function test_super_admin_changes_plan_and_suspension(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'business_name' => 'Spa',
            'slug' => 'spa-a',
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'password',
        ])->assertCreated();

        app(TenantContext::class)->forget();
        $admin = User::withoutGlobalScope('tenant')->create([
            'name' => 'Root',
            'email' => 'root@example.com',
            'password' => 'password',
            'tenant_id' => null,
            'is_super_admin' => true,
            'email_verified_at' => now(),
        ]);

        $this->login('root@example.com');
        $tenant = Tenant::query()->where('slug', 'spa-a')->firstOrFail();

        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/admin/tenants')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'spa-a']);

        $this->withHeader('Referer', 'http://localhost:5173')
            ->patchJson('/api/v1/admin/tenants/'.$tenant->public_id, ['plan' => 'pro'])
            ->assertOk()
            ->assertJsonPath('plan', 'pro');
        $this->assertSame('pro', $tenant->fresh()->plan);

        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/admin/tenants/'.$tenant->public_id.'/suspend')
            ->assertOk();

        $this->logout();
        $this->withHeader('Referer', 'http://localhost:5173')->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'password',
        ])->assertForbidden();
        $this->getJson('/api/v1/public/spa-a/branches')->assertForbidden();

        $this->login($admin->email);
        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/admin/tenants/'.$tenant->public_id.'/unsuspend')
            ->assertOk()
            ->assertJsonPath('suspended_at', null);

        $this->logout();
        $this->login('owner@example.com');
        $this->getJson('/api/v1/public/spa-a/branches')->assertOk();
    }

    private function registerAndLogin(string $email, string $slug): void
    {
        $this->postJson('/api/v1/auth/register', [
            'business_name' => 'Spa',
            'slug' => $slug,
            'name' => 'Owner',
            'email' => $email,
            'password' => 'password',
        ])->assertCreated();
        $this->login($email);
    }

    private function login(string $email): void
    {
        $this->withHeader('Referer', 'http://localhost:5173')->get('/sanctum/csrf-cookie');
        $this->withHeaders([
            'Referer' => 'http://localhost:5173',
            'X-XSRF-TOKEN' => csrf_token(),
        ])->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'password',
        ])->assertOk();
    }

    private function logout(): void
    {
        $this->withHeaders([
            'Referer' => 'http://localhost:5173',
            'X-XSRF-TOKEN' => csrf_token(),
        ])->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        app(TenantContext::class)->forget();
    }
}
