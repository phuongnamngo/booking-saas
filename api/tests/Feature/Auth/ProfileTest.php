<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_owner_updates_name_and_password(): void
    {
        $this->register('an@example.com', 'spa-a');
        $this->verifyRegisteredUsers();
        $this->login('an@example.com', 'password');

        $this->withHeader('Referer', 'http://localhost:5173')
            ->patchJson('/api/v1/auth/profile', ['name' => 'An Mới'])
            ->assertOk()
            ->assertJsonPath('name', 'An Mới');

        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('name', 'An Mới');

        $this->withHeader('Referer', 'http://localhost:5173')
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])->assertUnprocessable();

        $this->withHeader('Referer', 'http://localhost:5173')
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])->assertOk();

        $this->logout();
        $this->login('an@example.com', 'password', 422);
        $this->login('an@example.com', 'new-password');
    }

    public function test_super_admin_updates_name_and_password(): void
    {
        app(TenantContext::class)->forget();
        $admin = User::withoutGlobalScope('tenant')->create([
            'name' => 'Root',
            'email' => 'root@example.com',
            'password' => 'password',
            'tenant_id' => null,
            'is_super_admin' => true,
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();
        $this->login('root@example.com', 'password');

        $this->withHeader('Referer', 'http://localhost:5173')
            ->patchJson('/api/v1/auth/profile', ['name' => 'Root Mới'])
            ->assertOk()
            ->assertJsonPath('name', 'Root Mới');

        $this->withHeader('Referer', 'http://localhost:5173')
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])->assertOk();

        $this->logout();
        $this->login('root@example.com', 'new-password');
        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('name', 'Root Mới')
            ->assertJsonPath('is_super_admin', true);
    }

    public function test_unverified_user_can_change_password_but_not_open_branches(): void
    {
        $this->register('b@example.com', 'spa-b');
        $this->login('b@example.com', 'password');

        $this->withHeader('Referer', 'http://localhost:5173')
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])->assertOk();

        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/branches')
            ->assertForbidden();
    }

    private function register(string $email, string $slug): void
    {
        $this->postJson('/api/v1/auth/register', [
            'business_name' => 'Spa',
            'slug' => $slug,
            'name' => 'An',
            'email' => $email,
            'password' => 'password',
        ])->assertCreated();
    }

    private function login(string $email, string $password, int $status = 200): void
    {
        $this->withHeader('Referer', 'http://localhost:5173')->get('/sanctum/csrf-cookie');
        $this->withHeaders([
            'Referer' => 'http://localhost:5173',
            'X-XSRF-TOKEN' => csrf_token(),
        ])->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => $password,
        ])->assertStatus($status);
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
