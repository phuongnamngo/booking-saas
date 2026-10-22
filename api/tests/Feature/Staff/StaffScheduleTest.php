<?php

namespace Tests\Feature\Staff;

use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StaffScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_working_hours_and_off_exception_are_stored(): void
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

        $this->withHeader('Referer', 'http://localhost:5173')->get('/sanctum/csrf-cookie');
        $this->withHeaders([
            'Referer' => 'http://localhost:5173',
            'X-XSRF-TOKEN' => csrf_token(),
        ])->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'password',
        ])->assertOk();

        $tenant = Tenant::query()->where('slug', 'spa-a')->firstOrFail();
        app(TenantContext::class)->set($tenant);
        setPermissionsTeamId($tenant->id);
        $user = User::factory()->create([
            'email' => 'staff@example.com',
            'password' => 'password',
            'tenant_id' => $tenant->id,
        ]);
        $user->assignRole('staff');

        $staffId = $this->withHeader('Referer', 'http://localhost:5173')->postJson('/api/v1/staff', [
            'user_id' => $user->public_id,
            'branch_ids' => [],
            'service_ids' => [],
        ])->assertCreated()->json('id');

        $this->withHeader('Referer', 'http://localhost:5173')
            ->putJson('/api/v1/staff/'.$staffId.'/working-hours', [
                'intervals' => [
                    ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '12:00'],
                ],
            ])
            ->assertSuccessful();

        $profile = StaffProfile::query()->where('public_id', $staffId)->firstOrFail();
        $this->assertSame(1, DB::table('staff_working_hours')->where('staff_profile_id', $profile->id)->count());

        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/staff/'.$staffId.'/exceptions', [
                'type' => 'off',
                'starts_at' => '2026-10-27T09:00:00+07:00',
                'ends_at' => '2026-10-27T12:00:00+07:00',
            ])
            ->assertCreated();

        $this->assertSame(1, DB::table('staff_schedule_exceptions')->where('staff_profile_id', $profile->id)->count());
    }
}
