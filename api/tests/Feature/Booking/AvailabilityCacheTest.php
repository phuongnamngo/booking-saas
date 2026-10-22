<?php

namespace Tests\Feature\Booking;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchOpeningHour;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWorkingHour;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AvailabilityCache;
use App\Support\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AvailabilityCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_cache_does_not_double_book_and_opening_hours_drop_the_key(): void
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

        $payload = [
            'branch_id' => $branch->public_id,
            'staff_id' => $staff->public_id,
            'service_ids' => [$service->public_id],
            'starts_at' => '2026-10-26T02:00:00+00:00',
            'customer' => [
                'name' => 'Khách A',
                'phone' => '0901111222',
            ],
        ];

        $this->postJson('/api/v1/public/spa-a/appointments', $payload)->assertCreated();

        $key = AvailabilityCache::key($tenant->id, $branch->public_id, $staff->public_id, [$service->public_id], '2026-10-26');
        Cache::put($key, [[
            'starts_at' => '2026-10-26T02:00:00+00:00',
            'ends_at' => '2026-10-26T02:30:00+00:00',
        ]]);

        $response = $this->postJson('/api/v1/public/spa-a/appointments', [
            ...$payload,
            'customer' => ['name' => 'Khách B', 'phone' => '0903333444'],
        ]);
        $response->assertStatus(409);
        $response->assertJsonPath('code', 'slot_unavailable');
        $this->assertSame(1, Appointment::query()->count());

        $this->withHeader('Referer', 'http://localhost:5173')->get('/sanctum/csrf-cookie');
        $this->withHeaders([
            'Referer' => 'http://localhost:5173',
            'X-XSRF-TOKEN' => csrf_token(),
        ])->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->withHeader('Referer', 'http://localhost:5173')
            ->putJson('/api/v1/branches/'.$branch->public_id.'/opening-hours', [
                'intervals' => [
                    ['weekday' => 1, 'opens_at' => '09:00', 'closes_at' => '12:00'],
                ],
            ])
            ->assertOk();

        $this->assertFalse(Cache::has($key));
    }
}
