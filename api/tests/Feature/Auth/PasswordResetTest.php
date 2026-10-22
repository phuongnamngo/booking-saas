<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_unknown_email_returns_the_same_success_payload(): void
    {
        Notification::fake();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'missing@example.com',
        ])->assertOk()->assertJsonPath('message', 'Nếu email tồn tại, hướng dẫn đã được gửi.');

        Notification::assertNothingSent();
    }

    public function test_reset_link_points_at_the_frontend_and_changes_the_password(): void
    {
        Notification::fake();
        $this->postJson('/api/v1/auth/register', [
            'business_name' => 'Spa',
            'slug' => 'spa-a',
            'name' => 'An',
            'email' => 'an@example.com',
            'password' => 'password',
        ])->assertCreated();
        $user = User::withoutGlobalScope('tenant')->where('email', 'an@example.com')->firstOrFail();

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'an@example.com',
        ])->assertOk();

        $token = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user, &$token): bool {
            $token = $notification->token;
            $url = $notification->toMail($user)->actionUrl;

            return str_starts_with($url, 'http://localhost:5173/reset-password?token=');
        });

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'an@example.com',
            'token' => 'not-the-token',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertUnprocessable()->assertJsonValidationErrors('token');

        $this->postJson('/api/v1/auth/reset-password', [
            'email' => 'an@example.com',
            'token' => $token,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_forgot_password_is_rate_limited(): void
    {
        Notification::fake();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/v1/auth/forgot-password', [
                'email' => 'limited@example.com',
            ])->assertOk();
        }

        $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'limited@example.com',
        ])->assertTooManyRequests();
    }
}
