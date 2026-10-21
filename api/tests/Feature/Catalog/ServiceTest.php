<?php

namespace Tests\Feature\Catalog;

use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
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
    }

    public function test_service_without_category_returns_ulid_only(): void
    {
        $response = $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/services', [
                'name' => 'Cắt tóc',
                'duration_minutes' => 30,
                'price_amount' => 150000,
            ]);

        $response->assertCreated();
        $id = $response->json('id');
        $this->assertIsString($id);
        $this->assertSame(26, strlen($id));
        $this->assertNull($response->json('category_id'));
        $this->assertSame(150000, $response->json('price_amount'));
    }
}
