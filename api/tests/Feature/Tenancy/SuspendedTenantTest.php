<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Support\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuspendedTenantTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_wrong_password_stays_a_credential_error_when_the_tenant_is_suspended(): void
    {
        $this->register('a@example.com', 'spa-a');
        Tenant::query()->where('slug', 'spa-a')->update(['suspended_at' => now()]);

        $this->withHeader('Referer', 'http://localhost:5173')->postJson('/api/v1/auth/login', [
            'email' => 'a@example.com',
            'password' => 'nope',
        ])->assertUnprocessable()->assertJsonPath('message', 'Thông tin đăng nhập không đúng.');
    }

    public function test_correct_password_is_rejected_when_the_tenant_is_suspended(): void
    {
        $this->register('a@example.com', 'spa-a');
        Tenant::query()->where('slug', 'spa-a')->update(['suspended_at' => now()]);

        $this->withHeader('Referer', 'http://localhost:5173')->postJson('/api/v1/auth/login', [
            'email' => 'a@example.com',
            'password' => 'password',
        ])->assertForbidden()->assertJsonPath('message', 'Tenant đang bị khóa.');
    }

    public function test_existing_session_and_public_catalog_stop_after_suspension(): void
    {
        $this->register('a@example.com', 'spa-a');
        $this->login('a@example.com');
        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/branches', [
                'name' => 'Chi nhánh',
                'timezone' => 'Asia/Ho_Chi_Minh',
            ])->assertCreated();

        Tenant::query()->where('slug', 'spa-a')->update(['suspended_at' => now()]);
        app(TenantContext::class)->forget();

        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/branches')
            ->assertForbidden()
            ->assertJsonPath('message', 'Tenant đang bị khóa.');

        $this->getJson('/api/v1/public/spa-a/branches')
            ->assertForbidden()
            ->assertJsonPath('message', 'Tenant đang bị khóa.');
    }

    private function register(string $email, string $slug): void
    {
        $this->postJson('/api/v1/auth/register', [
            'business_name' => 'Spa',
            'slug' => $slug,
            'name' => 'Owner',
            'email' => $email,
            'password' => 'password',
        ])->assertCreated();
        $this->verifyRegisteredUsers();
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
}
