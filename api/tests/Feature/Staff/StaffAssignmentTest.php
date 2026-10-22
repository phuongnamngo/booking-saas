<?php

namespace Tests\Feature\Staff;

use App\Models\Branch;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StaffAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_manager_cannot_read_unassigned_branch_and_owner_can(): void
    {
        $this->registerAndLogin('owner@example.com', 'spa-a');

        $assignedId = $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/branches', [
                'name' => 'Chi nhánh được gán',
                'timezone' => 'Asia/Ho_Chi_Minh',
            ])->assertCreated()->json('id');

        $otherId = $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/branches', [
                'name' => 'Chi nhánh khác',
                'timezone' => 'Asia/Ho_Chi_Minh',
            ])->assertCreated()->json('id');

        $tenant = Tenant::query()->where('slug', 'spa-a')->firstOrFail();
        app(TenantContext::class)->set($tenant);
        setPermissionsTeamId($tenant->id);

        $manager = User::factory()->create([
            'name' => 'Quản lý',
            'email' => 'manager@example.com',
            'password' => 'password',
            'tenant_id' => $tenant->id,
        ]);
        $manager->assignRole('manager');

        $assigned = Branch::query()->where('public_id', $assignedId)->firstOrFail();
        DB::table('branch_user')->insert([
            'tenant_id' => $tenant->id,
            'branch_id' => $assigned->id,
            'user_id' => $manager->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withHeaders([
            'Referer' => 'http://localhost:5173',
            'X-XSRF-TOKEN' => csrf_token(),
        ])->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->login('manager@example.com');

        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/branches/'.$otherId)
            ->assertForbidden();

        $this->withHeaders([
            'Referer' => 'http://localhost:5173',
            'X-XSRF-TOKEN' => csrf_token(),
        ])->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->login('owner@example.com');

        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/branches/'.$otherId)
            ->assertOk();
    }

    public function test_owner_creates_staff_profile_with_branch_and_service(): void
    {
        $this->registerAndLogin('owner@example.com', 'spa-a');

        $branchId = $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/branches', [
                'name' => 'Chi nhánh 1',
                'timezone' => 'Asia/Ho_Chi_Minh',
            ])->assertCreated()->json('id');

        $serviceId = $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/services', [
                'name' => 'Cắt tóc',
                'duration_minutes' => 30,
                'price_amount' => 150000,
            ])->assertCreated()->json('id');

        $tenant = Tenant::query()->where('slug', 'spa-a')->firstOrFail();
        app(TenantContext::class)->set($tenant);
        setPermissionsTeamId($tenant->id);
        $staffUser = User::factory()->create([
            'email' => 'staff@example.com',
            'password' => 'password',
            'tenant_id' => $tenant->id,
        ]);
        $staffUser->assignRole('staff');

        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/staff', [
                'user_id' => $staffUser->public_id,
                'branch_ids' => [$branchId],
                'service_ids' => [$serviceId],
            ])
            ->assertCreated()
            ->assertJsonPath('user_id', $staffUser->public_id)
            ->assertJsonPath('branch_ids.0', $branchId)
            ->assertJsonPath('service_ids.0', $serviceId);

        $this->assertSame(1, StaffProfile::query()->count());
    }

    private function registerAndLogin(string $email, string $slug): void
    {
        $this->postJson('/api/v1/auth/register', [
            'business_name' => 'Spa A',
            'slug' => $slug,
            'name' => 'Owner',
            'email' => $email,
            'password' => 'password',
        ])->assertCreated();
        $this->verifyRegisteredUsers();

        Tenant::query()->where('slug', $slug)->update(['plan' => 'pro']);

        $this->login($email);
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
}
