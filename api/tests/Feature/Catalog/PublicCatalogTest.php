<?php

namespace Tests\Feature\Catalog;

use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_public_catalog_returns_only_active_records_for_the_slug(): void
    {
        $this->getJson('/api/v1/public/missing/services')->assertNotFound();

        $this->postJson('/api/v1/auth/register', [
            'business_name' => 'Spa A',
            'slug' => 'spa-a',
            'name' => 'Spa A',
            'email' => 'a@example.com',
            'password' => 'password',
        ])->assertCreated();

        $this->withHeader('Referer', 'http://localhost:5173')->get('/sanctum/csrf-cookie');
        $this->withHeaders([
            'Referer' => 'http://localhost:5173',
            'X-XSRF-TOKEN' => csrf_token(),
        ])->postJson('/api/v1/auth/login', [
            'email' => 'a@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->withHeader('Referer', 'http://localhost:5173')->postJson('/api/v1/services', [
            'name' => 'Cắt tóc',
            'duration_minutes' => 30,
            'price_amount' => 150000,
            'is_active' => true,
        ])->assertCreated();

        $this->withHeader('Referer', 'http://localhost:5173')->postJson('/api/v1/services', [
            'name' => 'Dịch vụ ẩn',
            'duration_minutes' => 30,
            'price_amount' => 100000,
            'is_active' => false,
        ])->assertCreated();

        $this->withHeader('Referer', 'http://localhost:5173')->postJson('/api/v1/branches', [
            'name' => 'Chi nhánh mở',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'is_active' => true,
        ])->assertCreated();

        $this->withHeader('Referer', 'http://localhost:5173')->postJson('/api/v1/branches', [
            'name' => 'Chi nhánh đóng',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'is_active' => false,
        ])->assertCreated();

        $services = $this->getJson('/api/v1/public/spa-a/services')->assertOk()->json();
        $this->assertSame(['Cắt tóc'], array_column($services, 'name'));

        $branches = $this->getJson('/api/v1/public/spa-a/branches')->assertOk()->json();
        $this->assertSame(['Chi nhánh mở'], array_column($branches, 'name'));
    }
}
