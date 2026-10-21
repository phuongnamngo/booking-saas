<?php

namespace Tests\Feature\Booking;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentRangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_week_range_is_at_most_seven_days_and_keeps_the_date_query(): void
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

        $tenant = Tenant::query()->where('slug', 'spa-a')->firstOrFail();
        app(TenantContext::class)->set($tenant);
        setPermissionsTeamId($tenant->id);
        $branch = Branch::query()->create([
            'name' => 'Chi nhánh 1',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'is_active' => true,
        ]);
        $service = Service::query()->create([
            'name' => 'Cắt tóc',
            'duration_minutes' => 30,
            'price_amount' => 150000,
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'email' => 'staff@example.com',
            'password' => 'password',
            'tenant_id' => $tenant->id,
        ]);
        $user->assignRole('staff');
        $user->branches()->attach($branch->id, ['tenant_id' => $tenant->id]);
        $staff = StaffProfile::query()->create(['user_id' => $user->id]);
        $staff->services()->attach($service->id, ['tenant_id' => $tenant->id]);
        $inside = $this->appointment($branch, $staff, '2026-10-26 09:00:00');
        $outside = $this->appointment($branch, $staff, '2026-11-02 09:00:00');

        $manager = User::factory()->create([
            'email' => 'manager@example.com',
            'password' => 'password',
            'tenant_id' => $tenant->id,
        ]);
        $manager->assignRole('manager');

        $this->login('owner@example.com');
        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/appointments?from=2026-10-26&to=2026-11-02&branch_id='.$branch->public_id)
            ->assertStatus(422);

        $listed = $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/appointments?from=2026-10-26&to=2026-11-01&branch_id='.$branch->public_id)
            ->assertOk()
            ->json();
        $ids = array_column($listed, 'id');
        $this->assertContains($inside->public_id, $ids);
        $this->assertNotContains($outside->public_id, $ids);

        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/appointments?date=2026-10-26&branch_id='.$branch->public_id)
            ->assertOk()
            ->assertJsonFragment(['id' => $inside->public_id]);

        $this->logout();
        $this->login('manager@example.com');
        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/appointments?from=2026-10-26&to=2026-11-01&branch_id='.$branch->public_id)
            ->assertForbidden();
    }

    private function appointment(Branch $branch, StaffProfile $staff, string $localStart): Appointment
    {
        $customer = Customer::query()->create([
            'name' => 'Khách A',
            'phone' => '090'.random_int(1000000, 9999999),
        ]);
        $startsAt = CarbonImmutable::parse($localStart, $branch->timezone);

        return Appointment::query()->create([
            'branch_id' => $branch->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes(30),
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
