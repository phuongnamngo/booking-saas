<?php

namespace Tests\Feature\Booking;

use App\Models\Branch;
use App\Models\BranchOpeningHour;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWorkingHour;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AppointmentBooker;
use App\Support\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RescheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_reschedule_moves_the_appointment_to_another_staff_member(): void
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
        $first = $this->staff($tenant->id, $branch->id, $service->id, 'one@example.com');
        $second = $this->staff($tenant->id, $branch->id, $service->id, 'two@example.com');

        $created = $this->postJson('/api/v1/public/spa-a/appointments', [
            'branch_id' => $branch->public_id,
            'staff_id' => $first->public_id,
            'service_ids' => [$service->public_id],
            'starts_at' => '2026-10-26T02:00:00+00:00',
            'customer' => ['name' => 'Khách', 'phone' => '0901111222'],
        ])->assertCreated();

        $this->withHeader('Referer', 'http://localhost:5173')->get('/sanctum/csrf-cookie');
        $this->withHeaders([
            'Referer' => 'http://localhost:5173',
            'X-XSRF-TOKEN' => csrf_token(),
        ])->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->withHeader('Referer', 'http://localhost:5173')
            ->patchJson('/api/v1/appointments/'.$created->json('id'), [
                'staff_id' => $second->public_id,
                'starts_at' => '2026-10-26T02:30:00+00:00',
            ])
            ->assertOk()
            ->assertJsonPath('staff_id', $second->public_id);
    }

    public function test_staff_locks_are_taken_in_ascending_id_order(): void
    {
        $this->assertSame([4, 20], app(AppointmentBooker::class)->staffIdsForUpdate(20, 4));
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
}
