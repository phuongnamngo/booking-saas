<?php

namespace Tests\Feature\Catalog;

use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_other_tenant_cannot_read_branch_and_guests_are_unauthorized(): void
    {
        $this->register('Spa A', 'spa-a', 'a@example.com');
        $this->login('a@example.com');

        $branchId = $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/branches', [
                'name' => 'Chi nhánh 1',
                'timezone' => 'Asia/Ho_Chi_Minh',
            ])
            ->assertCreated()
            ->json('id');

        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/branches')
            ->assertOk()
            ->assertJsonFragment(['id' => $branchId]);

        $this->withHeaders([
            'Referer' => 'http://localhost:5173',
            'X-XSRF-TOKEN' => csrf_token(),
        ])->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();

        $this->register('Spa B', 'spa-b', 'b@example.com');
        $this->login('b@example.com');

        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/branches/'.$branchId)
            ->assertNotFound();
    }

    public function test_app_route_without_session_is_unauthorized(): void
    {
        $this->getJson('/api/v1/branches')->assertUnauthorized();
    }

    private function register(string $business, string $slug, string $email): void
    {
        $this->postJson('/api/v1/auth/register', [
            'business_name' => $business,
            'slug' => $slug,
            'name' => $business,
            'email' => $email,
            'password' => 'password',
        ])->assertCreated();
    }

    private function login(string $email): void
    {
        $this->withHeader('Referer', 'http://localhost:5173')
            ->get('/sanctum/csrf-cookie');

        $this->withHeaders([
            'Referer' => 'http://localhost:5173',
            'X-XSRF-TOKEN' => csrf_token(),
        ])->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'password',
        ])->assertOk();
    }
}
