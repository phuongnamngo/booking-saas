<?php

namespace Tests\Feature\Invitations;

use App\Mail\StaffInvitationMail;
use App\Models\Invitation;
use App\Models\StaffProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Mail::fake();
        $this->postJson('/api/v1/auth/register', [
            'business_name' => 'Spa A',
            'slug' => 'spa-a',
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'password',
        ])->assertCreated();
        $this->withHeader('Referer', 'http://localhost:5173')->get('/sanctum/csrf-cookie');
        $this->withHeaders([
            'Referer' => 'http://localhost:5173',
            'X-XSRF-TOKEN' => csrf_token(),
        ])->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'password',
        ])->assertOk();
    }

    public function test_staff_invitation_creates_profile_and_manager_invitation_does_not(): void
    {
        $staffToken = $this->invite('staff@example.com', 'staff');
        $this->assertStringNotContainsString($staffToken, Invitation::query()->firstOrFail()->toJson());

        $this->postJson('/api/v1/invitations/accept', [
            'token' => $staffToken,
            'name' => 'Nhân viên',
            'password' => 'password',
        ])->assertCreated();

        $staff = User::withoutGlobalScope('tenant')->where('email', 'staff@example.com')->firstOrFail();
        $owner = User::withoutGlobalScope('tenant')->where('email', 'owner@example.com')->firstOrFail();
        $this->assertSame($owner->tenant_id, $staff->tenant_id);
        setPermissionsTeamId($staff->tenant_id);
        $this->assertTrue($staff->hasRole('staff'));
        $this->assertSame(1, StaffProfile::query()->where('user_id', $staff->id)->count());

        $this->postJson('/api/v1/invitations/accept', [
            'token' => $staffToken,
            'name' => 'Nhân viên',
            'password' => 'password',
        ])->assertUnprocessable();

        $managerToken = $this->invite('manager@example.com', 'manager');
        $this->postJson('/api/v1/invitations/accept', [
            'token' => $managerToken,
            'name' => 'Quản lý',
            'password' => 'password',
        ])->assertCreated();

        $manager = User::withoutGlobalScope('tenant')->where('email', 'manager@example.com')->firstOrFail();
        $this->assertSame(0, StaffProfile::query()->where('user_id', $manager->id)->count());

        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/invitations', [
                'email' => 'owner@example.com',
                'role' => 'staff',
            ])->assertUnprocessable();
    }

    public function test_expired_invitation_cannot_be_accepted(): void
    {
        $token = $this->invite('late@example.com', 'staff');
        Invitation::query()->update(['expires_at' => now()->subMinute()]);

        $this->postJson('/api/v1/invitations/accept', [
            'token' => $token,
            'name' => 'Muộn',
            'password' => 'password',
        ])->assertUnprocessable();
    }

    private function invite(string $email, string $role): string
    {
        $response = $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/invitations', [
                'email' => $email,
                'role' => $role,
            ]);

        $response->assertCreated();
        $this->assertNull($response->json('token'));

        $plain = null;
        Mail::assertSent(StaffInvitationMail::class, function (StaffInvitationMail $mail) use (&$plain, $email): bool {
            if ($mail->hasTo($email)) {
                $plain = $mail->token;

                return true;
            }

            return false;
        });

        return $plain;
    }
}
