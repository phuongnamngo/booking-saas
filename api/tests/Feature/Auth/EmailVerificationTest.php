<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_unverified_owner_is_blocked_until_the_signed_link_is_opened(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'business_name' => 'Spa',
            'slug' => 'spa-a',
            'name' => 'An',
            'email' => 'an@example.com',
            'password' => 'password',
        ])->assertCreated();

        $user = User::withoutGlobalScope('tenant')->where('email', 'an@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);

        $this->login('an@example.com');
        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/branches')
            ->assertForbidden();

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->public_id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->get($url)->assertRedirect('http://localhost:5173/verify-email');
        $this->assertNotNull($user->fresh()->email_verified_at);

        $this->app['auth']->forgetGuards();

        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/branches')
            ->assertOk();
    }

    public function test_signed_in_user_can_resend_verification(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'business_name' => 'Spa',
            'slug' => 'spa-b',
            'name' => 'An',
            'email' => 'b@example.com',
            'password' => 'password',
        ])->assertCreated();
        $this->login('b@example.com');

        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/auth/email/verification-notification')
            ->assertStatus(202);
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
