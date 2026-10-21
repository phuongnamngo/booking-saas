<?php

namespace Tests\Feature\Auth;

use App\Support\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_failed_login_hides_which_field_was_wrong(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'missing@example.com',
            'password' => 'password',
        ])->assertStatus(422)->assertJsonPath('message', 'Thông tin đăng nhập không đúng.');
    }

    public function test_login_then_me_then_logout(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'business_name' => 'Spa Một',
            'slug' => 'spa-mot',
            'name' => 'An',
            'email' => 'an@example.com',
            'password' => 'password',
        ])->assertCreated();

        $this->withHeader('Referer', 'http://localhost:5173')
            ->get('/sanctum/csrf-cookie')
            ->assertNoContent();

        $this->withHeaders([
            'Referer' => 'http://localhost:5173',
            'X-XSRF-TOKEN' => csrf_token(),
        ])->postJson('/api/v1/auth/login', [
            'email' => 'an@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->app['auth']->forgetGuards();
        app(TenantContext::class)->forget();

        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('role', 'owner')
            ->assertJsonPath('tenant.slug', 'spa-mot');

        $this->withHeaders([
            'Referer' => 'http://localhost:5173',
            'X-XSRF-TOKEN' => csrf_token(),
        ])->postJson('/api/v1/auth/logout')
            ->assertNoContent();

        $this->app['auth']->forgetGuards();

        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/auth/me')
            ->assertUnauthorized();
    }
}
