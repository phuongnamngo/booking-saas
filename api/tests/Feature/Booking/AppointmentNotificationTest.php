<?php

namespace Tests\Feature\Booking;

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

class AppointmentNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_notifies_owner_assigned_manager_and_that_staff_only(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->fail('Overlap tests require MySQL.');
        }

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
        setPermissionsTeamId($tenant->id);
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
        $service = Service::query()->create([
            'name' => 'Cắt tóc',
            'duration_minutes' => 30,
            'price_amount' => 150000,
            'is_active' => true,
        ]);
        $staff = $this->staff($tenant->id, $branch->id, $service->id, 'staff@example.com');
        $this->staff($tenant->id, $branch->id, $service->id, 'other@example.com');
        $manager = User::factory()->create([
            'email' => 'manager@example.com',
            'password' => 'password',
            'tenant_id' => $tenant->id,
        ]);
        $manager->assignRole('manager');
        $manager->branches()->attach($branch->id, ['tenant_id' => $tenant->id]);

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

        $this->login('owner@example.com');
        $listed = $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->json();
        $this->assertSame('Lịch mới', $listed[0]['data']['message']);

        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/notifications/'.$listed[0]['id'].'/read')
            ->assertNoContent();
        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('0.read_at', fn ($readAt) => $readAt !== null);

        $this->logout();
        $this->login('other@example.com');
        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertExactJson([]);

        $this->logout();
        $this->login('staff@example.com');
        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('0.data.message', 'Lịch mới');

        $this->logout();
        $this->login('manager@example.com');
        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonPath('0.data.message', 'Lịch mới');
    }

    private function staff(int $tenantId, int $branchId, int $serviceId, string $email): StaffProfile
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => 'password',
            'tenant_id' => $tenantId,
        ]);
        $user->assignRole('staff');
        $user->branches()->attach($branchId, ['tenant_id' => $tenantId]);
        $profile = StaffProfile::query()->create(['user_id' => $user->id]);
        StaffWorkingHour::query()->create([
            'staff_profile_id' => $profile->id,
            'weekday' => 1,
            'starts_at' => '09:00',
            'ends_at' => '12:00',
        ]);
        $profile->services()->attach($serviceId, ['tenant_id' => $tenantId]);

        return $profile;
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

    private function logout(): void
    {
        $this->withHeaders([
            'Referer' => 'http://localhost:5173',
            'X-XSRF-TOKEN' => csrf_token(),
        ])->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
    }
}
