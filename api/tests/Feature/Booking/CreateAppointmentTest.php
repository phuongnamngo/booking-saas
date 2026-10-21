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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAppointmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_booking_creates_a_customer_and_not_a_user(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->fail('Overlap tests require MySQL.');
        }

        $tenant = Tenant::factory()->create(['slug' => 'spa-a']);
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
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
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

        $usersBefore = User::withoutGlobalScope('tenant')->count();

        $response = $this->postJson('/api/v1/public/spa-a/appointments', [
            'branch_id' => $branch->public_id,
            'staff_id' => $staff->public_id,
            'service_ids' => [$service->public_id],
            'starts_at' => '2026-10-26T02:00:00+00:00',
            'customer' => [
                'name' => 'Khách A',
                'phone' => ' 0901111222 ',
                'email' => 'khach@example.com',
            ],
        ]);

        $response->assertCreated();
        $this->assertSame($usersBefore, User::withoutGlobalScope('tenant')->count());
        $this->assertSame(1, Customer::query()->count());
        $this->assertSame('0901111222', Customer::query()->first()->phone);

        $appointment = Appointment::query()->firstOrFail();
        $this->assertNull($appointment->created_by);
        $this->assertSame('pending', $appointment->status);
        $this->assertSame($response->json('id'), $appointment->public_id);
        $this->assertSame(150000, $appointment->services()->first()->price_amount);
        $this->assertSame(30, $appointment->services()->first()->duration_minutes);
    }
}
