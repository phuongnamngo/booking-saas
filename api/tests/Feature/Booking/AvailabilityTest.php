<?php

namespace Tests\Feature\Booking;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchHoliday;
use App\Models\BranchOpeningHour;
use App\Models\Customer;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffScheduleException;
use App\Models\StaffWorkingHour;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_holiday_and_time_off_remove_slots(): void
    {
        [$branch, $staff, $service] = $this->openMonday();

        BranchHoliday::query()->create([
            'branch_id' => $branch->id,
            'date' => '2026-10-26',
            'name' => 'Nghỉ',
        ]);

        $this->assertSame([], $this->slots($branch, $staff, [$service->public_id]));

        BranchHoliday::query()->delete();
        StaffScheduleException::query()->create([
            'staff_profile_id' => $staff->id,
            'type' => 'off',
            'starts_at' => CarbonImmutable::parse('2026-10-26 09:00:00', 'Asia/Ho_Chi_Minh'),
            'ends_at' => CarbonImmutable::parse('2026-10-26 12:00:00', 'Asia/Ho_Chi_Minh'),
        ]);

        $this->assertSame([], $this->slots($branch, $staff, [$service->public_id]));
    }

    public function test_two_services_make_a_slot_as_long_as_their_sum(): void
    {
        [$branch, $staff, $first] = $this->openMonday();
        $second = Service::query()->create([
            'name' => 'Nhuộm',
            'duration_minutes' => 45,
            'price_amount' => 200000,
            'is_active' => true,
        ]);
        $staff->services()->attach($second->id, ['tenant_id' => $branch->tenant_id]);

        $slots = $this->slots($branch, $staff, [$first->public_id, $second->public_id]);
        $start = CarbonImmutable::parse($slots[0]['starts_at'])->timezone('Asia/Ho_Chi_Minh');
        $end = CarbonImmutable::parse($slots[0]['ends_at'])->timezone('Asia/Ho_Chi_Minh');

        $this->assertSame('09:00', $start->format('H:i'));
        $this->assertSame('10:15', $end->format('H:i'));
    }

    public function test_slot_starts_when_the_previous_appointment_ends(): void
    {
        [$branch, $staff, $service] = $this->openMonday();
        $customer = Customer::query()->create([
            'name' => 'Khách',
            'phone' => '0900000001',
        ]);
        Appointment::query()->create([
            'branch_id' => $branch->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'starts_at' => CarbonImmutable::parse('2026-10-26 09:00:00', 'Asia/Ho_Chi_Minh'),
            'ends_at' => CarbonImmutable::parse('2026-10-26 09:30:00', 'Asia/Ho_Chi_Minh'),
            'status' => 'confirmed',
        ]);

        $localStarts = collect($this->slots($branch, $staff, [$service->public_id]))->map(
            fn (array $slot) => CarbonImmutable::parse($slot['starts_at'])->timezone('Asia/Ho_Chi_Minh')->format('H:i'),
        );

        $this->assertFalse($localStarts->contains('09:00'));
        $this->assertTrue($localStarts->contains('09:30'));
    }

    public function test_unassigned_staff_and_staff_without_hours_return_an_empty_list(): void
    {
        [$branch, $staff, $service] = $this->openMonday();
        $staff->user->branches()->detach();

        $this->getJson($this->url($branch, $staff, [$service->public_id]))
            ->assertOk()
            ->assertJsonPath('slots', []);

        $staff->user->branches()->attach($branch->id, ['tenant_id' => $branch->tenant_id]);
        StaffWorkingHour::query()->delete();

        $this->getJson($this->url($branch, $staff, [$service->public_id]))
            ->assertOk()
            ->assertJsonPath('slots', []);
    }

    /**
     * @return array{0: Branch, 1: StaffProfile, 2: Service}
     */
    private function openMonday(): array
    {
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
        $staff->setRelation('user', $user);

        return [$branch, $staff, $service];
    }

    /**
     * @param  list<string>  $serviceIds
     * @return list<array{starts_at: string, ends_at: string}>
     */
    private function slots(Branch $branch, StaffProfile $staff, array $serviceIds): array
    {
        return $this->getJson($this->url($branch, $staff, $serviceIds))
            ->assertOk()
            ->json('slots');
    }

    /**
     * @param  list<string>  $serviceIds
     */
    private function url(Branch $branch, StaffProfile $staff, array $serviceIds): string
    {
        return '/api/v1/public/spa-a/availability?'.http_build_query([
            'branch_id' => $branch->public_id,
            'staff_id' => $staff->public_id,
            'service_ids' => $serviceIds,
            'date' => '2026-10-26',
        ]);
    }
}
