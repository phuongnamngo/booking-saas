<?php

namespace Tests\Feature\Booking;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchOpeningHour;
use App\Models\Customer;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWorkingHour;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_cannot_change_another_staff_members_appointment(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->fail('Overlap tests require MySQL.');
        }

        [$branch, $service, $ownerBranch] = $this->workspace();
        $first = $this->staff('a@example.com', $branch, $service);
        $second = $this->staff('b@example.com', $branch, $service);
        $appointment = $this->appointment($branch, $first, $service);

        $this->login('b@example.com');

        $this->withHeader('Referer', 'http://localhost:5173')
            ->patchJson('/api/v1/appointments/'.$appointment->public_id, [
                'starts_at' => '2026-10-26T03:00:00+00:00',
            ])
            ->assertForbidden();

        $this->assertNotSame($second->id, $appointment->fresh()->staff_id);
    }

    public function test_manager_cannot_list_an_unassigned_branch_and_owner_can(): void
    {
        [$assigned, $service, $other] = $this->workspace();
        $staff = $this->staff('staff@example.com', $other, $service);
        $appointment = $this->appointment($other, $staff, $service);

        $tenant = Tenant::query()->where('slug', 'spa-a')->firstOrFail();
        app(TenantContext::class)->set($tenant);
        setPermissionsTeamId($tenant->id);
        $manager = User::factory()->create([
            'email' => 'manager@example.com',
            'password' => 'password',
            'tenant_id' => $tenant->id,
        ]);
        $manager->assignRole('manager');
        $manager->branches()->attach($assigned->id, ['tenant_id' => $tenant->id]);

        $this->login('manager@example.com');
        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/appointments?date=2026-10-26&branch_id='.$other->public_id)
            ->assertForbidden();

        $this->logout();
        $this->login('owner@example.com');
        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/appointments?date=2026-10-26&branch_id='.$other->public_id)
            ->assertOk()
            ->assertJsonFragment(['id' => $appointment->public_id]);
    }

    public function test_completed_appointment_cannot_be_cancelled(): void
    {
        [$branch, $service] = array_slice($this->workspace(), 0, 2);
        $staff = $this->staff('staff@example.com', $branch, $service);
        $appointment = $this->appointment($branch, $staff, $service);
        $appointment->update(['status' => 'completed']);

        $this->login('owner@example.com');
        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/appointments/'.$appointment->public_id.'/cancel')
            ->assertUnprocessable();
    }

    /**
     * @return array{0: Branch, 1: Service, 2: Branch}
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
        setPermissionsTeamId($tenant->id);

        $assigned = Branch::query()->create([
            'name' => 'Chi nhánh được gán',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'is_active' => true,
        ]);
        $other = Branch::query()->create([
            'name' => 'Chi nhánh khác',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'is_active' => true,
        ]);
        foreach ([$assigned, $other] as $branch) {
            BranchOpeningHour::query()->create([
                'branch_id' => $branch->id,
                'weekday' => 1,
                'opens_at' => '09:00',
                'closes_at' => '18:00',
            ]);
        }
        $service = Service::query()->create([
            'name' => 'Cắt tóc',
            'duration_minutes' => 30,
            'price_amount' => 150000,
            'is_active' => true,
        ]);

        return [$assigned, $service, $other];
    }

    private function staff(string $email, Branch $branch, Service $service): StaffProfile
    {
        $tenantId = $branch->tenant_id;
        app(TenantContext::class)->set($branch->tenant);
        setPermissionsTeamId($tenantId);
        $user = User::factory()->create([
            'email' => $email,
            'password' => 'password',
            'tenant_id' => $tenantId,
        ]);
        $user->assignRole('staff');
        $user->branches()->attach($branch->id, ['tenant_id' => $tenantId]);
        $profile = StaffProfile::query()->create(['user_id' => $user->id]);
        StaffWorkingHour::query()->create([
            'staff_profile_id' => $profile->id,
            'weekday' => 1,
            'starts_at' => '09:00',
            'ends_at' => '18:00',
        ]);
        $profile->services()->attach($service->id, ['tenant_id' => $tenantId]);

        return $profile;
    }

    private function appointment(Branch $branch, StaffProfile $staff, Service $service): Appointment
    {
        app(TenantContext::class)->set($branch->tenant);
        $customer = Customer::query()->create([
            'name' => 'Khách',
            'phone' => '090'.random_int(1000000, 9999999),
        ]);

        return Appointment::query()->create([
            'branch_id' => $branch->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'starts_at' => CarbonImmutable::parse('2026-10-26 09:00:00', 'Asia/Ho_Chi_Minh'),
            'ends_at' => CarbonImmutable::parse('2026-10-26 09:30:00', 'Asia/Ho_Chi_Minh'),
            'status' => 'pending',
        ]);
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
