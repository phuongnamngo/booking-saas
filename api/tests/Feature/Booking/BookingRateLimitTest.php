<?php

namespace Tests\Feature\Booking;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class BookingRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear(md5('login127.0.0.1|owner@example.com'));
    }

    public function test_sixth_failed_login_is_throttled(): void
    {
        $this->withHeader('Referer', 'http://localhost:5173')->get('/sanctum/csrf-cookie');

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->withHeaders([
                'Referer' => 'http://localhost:5173',
                'X-XSRF-TOKEN' => csrf_token(),
            ])->postJson('/api/v1/auth/login', [
                'email' => 'owner@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $this->withHeaders([
            'Referer' => 'http://localhost:5173',
            'X-XSRF-TOKEN' => csrf_token(),
        ])->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }

    public function test_eleventh_booking_post_is_throttled(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'spa-a']);
        RateLimiter::clear(md5('booking127.0.0.1|'.$tenant->id));

        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $status = $this->postJson('/api/v1/public/spa-a/appointments', [])->status();
            $this->assertNotSame(429, $status);
        }

        $this->postJson('/api/v1/public/spa-a/appointments', [])->assertStatus(429);
    }
}
