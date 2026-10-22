<?php

namespace Tests\Feature\Booking;

use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchOpeningHour;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWorkingHour;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_create_and_owner_cancel_are_audited_without_secrets(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->fail('Overlap tests require MySQL.');
        }

        [$branch, $staff, $service] = $this->workspace();

        $this->postJson('/api/v1/public/spa-a/appointments', [
            'branch_id' => $branch->public_id,
            'staff_id' => $staff->public_id,
            'service_ids' => [$service->public_id],
            'starts_at' => '2026-10-26T02:00:00+00:00',
            'customer' => [
                'name' => 'Khách A',
                'phone' => '0901111222',
                'email' => 'khach@example.com',
            ],
        ])->assertCreated();

        $appointmentId = Appointment::query()->firstOrFail()->public_id;
        $this->login('owner@example.com');
        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/appointments/'.$appointmentId.'/cancel')
            ->assertOk();

        $created = AuditLog::query()->where('action', 'appointment.created')->firstOrFail();
        $this->assertNull($created->actor_user_id);

        $owner = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $cancelled = AuditLog::query()->where('action', 'appointment.cancelled')->firstOrFail();
        $this->assertSame($owner->id, (int) $cancelled->actor_user_id);
        $this->assertSame($appointmentId, $cancelled->subject_public_id);

        foreach ([$created, $cancelled] as $log) {
            $encoded = json_encode($log->only(['before', 'after']));
            $this->assertIsString($encoded);
            $this->assertStringNotContainsString('password', $encoded);
            $this->assertStringNotContainsString('token', $encoded);
        }
    }

    /**
     * @return array{0: Branch, 1: StaffProfile, 2: Service}
     */
    private function workspace(): array
    {
        $this->seed(PermissionSeeder::class);
        $this->postJson('/api/v1/auth/register', [
            'business_name' => 'Spa A',
            'slug' => 'spa-a',
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'password',
        ])->assertCreated();
        $this->verifyRegisteredUsers();

        $tenant = Tenant::query()->where('slug', 'spa-a')->firstOrFail();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::query()->create([
            'name' => 'Chi nhánh 1',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'is_active' => true,
        ]);
        BranchOpeningHour::query()->create([
            'branch_id' => $branch->id,
            'weekday' => 1,
            'opens_at' => '09:00',
            'closes_at' => '12:00',
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'staff@example.com',
        ]);
        $user->branches()->attach($branch->id, ['tenant_id' => $tenant->id]);
        $staff = StaffProfile::query()->create(['user_id' => $user->id]);
        StaffWorkingHour::query()->create([
            'staff_profile_id' => $staff->id,
            'weekday' => 1,
            'starts_at' => '09:00',
            'ends_at' => '12:00',
        ]);
        $service = Service::query()->create([
            'name' => 'Cắt tóc',
            'duration_minutes' => 30,
            'price_amount' => 150000,
            'is_active' => true,
        ]);
        $staff->services()->attach($service->id, ['tenant_id' => $tenant->id]);

        return [$branch, $staff, $service];
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
