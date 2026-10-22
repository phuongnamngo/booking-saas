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
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class GuestBookingTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private StaffProfile $staff;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

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
        $this->branch = Branch::query()->create([
            'name' => 'Chi nhánh 1',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'is_active' => true,
        ]);
        BranchOpeningHour::query()->create([
            'branch_id' => $this->branch->id,
            'weekday' => 1,
            'opens_at' => '09:00',
            'closes_at' => '12:00',
        ]);
        $user = User::factory()->create([
            'email' => 'staff@example.com',
            'password' => 'password',
            'tenant_id' => $tenant->id,
        ]);
        setPermissionsTeamId($tenant->id);
        $user->assignRole('staff');
        $user->branches()->attach($this->branch->id, ['tenant_id' => $tenant->id]);
        $this->staff = StaffProfile::query()->create(['user_id' => $user->id]);
        StaffWorkingHour::query()->create([
            'staff_profile_id' => $this->staff->id,
            'weekday' => 1,
            'starts_at' => '09:00',
            'ends_at' => '12:00',
        ]);
        $this->service = Service::query()->create([
            'name' => 'Cắt tóc',
            'duration_minutes' => 30,
            'price_amount' => 150000,
            'is_active' => true,
        ]);
        $this->staff->services()->attach($this->service->id, ['tenant_id' => $tenant->id]);
    }

    public function test_same_phone_reuses_the_customer_and_updates_the_name(): void
    {
        $this->book('0901111222', 'Khách A', '2026-10-26T02:00:00+00:00')->assertCreated();
        $this->book(' 0901111222 ', 'Khách B', '2026-10-26T02:30:00+00:00')->assertCreated();

        $this->assertSame(1, Customer::query()->count());
        $this->assertSame('Khách B', Customer::query()->first()->name);
    }

    public function test_owner_sees_a_guest_booking_on_the_day_list(): void
    {
        $created = $this->book('0901111222', 'Khách A', '2026-10-26T02:00:00+00:00')->assertCreated();
        $this->loginOwner();

        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/appointments?date=2026-10-26&branch_id='.$this->branch->public_id)
            ->assertOk()
            ->assertJsonFragment(['id' => $created->json('id')]);
    }

    public function test_second_booking_on_the_same_slot_conflicts(): void
    {
        $this->book('0901111222', 'Khách A', '2026-10-26T02:00:00+00:00')->assertCreated();

        $this->book('0903333444', 'Khách B', '2026-10-26T02:00:00+00:00')
            ->assertStatus(409)
            ->assertJsonPath('code', 'slot_unavailable')
            ->assertJsonPath('message', 'Khung giờ không còn trống.');

        $this->assertSame(1, Appointment::query()->count());
    }

    public function test_past_start_is_rejected(): void
    {
        $this->book('0901111222', 'Khách A', '2020-01-06T02:00:00+00:00')->assertUnprocessable();
    }

    private function book(string $phone, string $name, string $startsAt): TestResponse
    {
        return $this->postJson('/api/v1/public/spa-a/appointments', [
            'branch_id' => $this->branch->public_id,
            'staff_id' => $this->staff->public_id,
            'service_ids' => [$this->service->public_id],
            'starts_at' => $startsAt,
            'customer' => [
                'name' => $name,
                'phone' => $phone,
            ],
        ]);
    }

    private function loginOwner(): void
    {
        $this->withHeader('Referer', 'http://localhost:5173')->get('/sanctum/csrf-cookie');
        $this->withHeaders([
            'Referer' => 'http://localhost:5173',
            'X-XSRF-TOKEN' => csrf_token(),
        ])->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'password',
        ])->assertOk();
    }
}
