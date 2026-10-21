<?php

namespace Tests\Feature\Booking;

use App\Models\Branch;
use App\Models\BranchOpeningHour;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWorkingHour;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_slots_use_a_15_minute_grid_and_must_fit_inside_the_window(): void
    {
        $tenant = Tenant::factory()->create();
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

        $slots = app(AvailabilityService::class)->slots($branch, $staff, collect([$service]), '2026-10-26');

        $this->assertSame('2026-10-26 02:00:00', $slots[0]['starts_at']->utc()->format('Y-m-d H:i:s'));
        $this->assertSame('09:15', $slots[1]['starts_at']->timezone('Asia/Ho_Chi_Minh')->format('H:i'));
        $localStarts = collect($slots)->map(
            fn (array $slot) => $slot['starts_at']->timezone('Asia/Ho_Chi_Minh')->format('H:i'),
        );
        $this->assertTrue($localStarts->contains('11:30'));
        $this->assertFalse($localStarts->contains('11:45'));
    }
}
