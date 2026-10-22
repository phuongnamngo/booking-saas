<?php

namespace Tests\Feature\Tenancy;

use App\Models\Branch;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PlanLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_free_tenant_cannot_create_a_second_branch(): void
    {
        $this->registerAndLogin('a@example.com', 'spa-a');

        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/branches', [
                'name' => 'Chi nhánh 1',
                'timezone' => 'Asia/Ho_Chi_Minh',
            ])
            ->assertCreated();

        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/branches', [
                'name' => 'Chi nhánh 2',
                'timezone' => 'Asia/Ho_Chi_Minh',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan')
            ->assertJsonPath('errors.plan.0', 'Gói Free chỉ có 1 chi nhánh.');
    }

    public function test_inactive_branch_still_uses_a_free_slot(): void
    {
        $this->registerAndLogin('b@example.com', 'spa-b');
        $tenant = Tenant::query()->where('slug', 'spa-b')->firstOrFail();
        app(TenantContext::class)->set($tenant);
        Branch::query()->create([
            'name' => 'Đã tắt',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'is_active' => false,
        ]);

        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/branches', [
                'name' => 'Chi nhánh mới',
                'timezone' => 'Asia/Ho_Chi_Minh',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan');
    }

    public function test_free_tenant_cannot_create_a_fourth_staff_profile(): void
    {
        $this->registerAndLogin('staff@example.com', 'spa-staff');
        $branchId = $this->postBranch('Chi nhánh')->assertCreated()->json('id');
        $serviceId = $this->postService()->assertCreated()->json('id');
        $tenant = Tenant::query()->where('slug', 'spa-staff')->firstOrFail();
        app(TenantContext::class)->set($tenant);
        setPermissionsTeamId($tenant->id);

        foreach (['s1@example.com', 's2@example.com', 's3@example.com'] as $email) {
            $this->postStaff($this->staffUser($email, $tenant), $branchId, $serviceId)->assertCreated();
        }

        $this->postStaff($this->staffUser('s4@example.com', $tenant), $branchId, $serviceId)
            ->assertUnprocessable()
            ->assertJsonPath('errors.plan.0', 'Gói Free chỉ có 3 nhân viên.');
    }

    public function test_pro_tenant_accepts_up_to_the_configured_caps(): void
    {
        $this->registerAndLogin('pro@example.com', 'spa-pro');
        $tenant = Tenant::query()->where('slug', 'spa-pro')->firstOrFail();
        $tenant->update(['plan' => 'pro']);
        app(TenantContext::class)->set($tenant->fresh());

        $branchCap = (int) config('plans.pro.branches');
        for ($index = 0; $index < $branchCap - 1; $index++) {
            Branch::query()->create([
                'name' => 'Nhánh '.$index,
                'timezone' => 'Asia/Ho_Chi_Minh',
            ]);
        }

        $this->postBranch('Nhánh cuối')->assertCreated();
        $this->postBranch('Nhánh vượt')->assertUnprocessable()->assertJsonValidationErrors('plan');

        Branch::query()->delete();
        $serviceId = $this->postService()->assertCreated()->json('id');
        $branchId = $this->postBranch('Một nhánh')->assertCreated()->json('id');
        setPermissionsTeamId($tenant->id);
        $staffCap = (int) config('plans.pro.staff');
        for ($index = 0; $index < $staffCap - 1; $index++) {
            StaffProfile::query()->create([
                'user_id' => $this->staffUser('pro-staff-'.$index.'@example.com', $tenant)->id,
            ]);
        }

        $this->postStaff($this->staffUser('pro-last@example.com', $tenant), $branchId, $serviceId)->assertCreated();
        $this->postStaff($this->staffUser('pro-over@example.com', $tenant), $branchId, $serviceId)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('plan');
    }

    public function test_a_full_tenant_does_not_block_another_tenant(): void
    {
        $this->registerAndLogin('full@example.com', 'spa-full');
        $this->postBranch('Đầy')->assertCreated();
        $this->logout();
        $this->registerAndLogin('other@example.com', 'spa-other');
        $this->postBranch('Của tenant khác')->assertCreated();
    }

    public function test_downgrade_keeps_existing_branches(): void
    {
        $this->registerAndLogin('down@example.com', 'spa-down');
        $tenant = Tenant::query()->where('slug', 'spa-down')->firstOrFail();
        $tenant->update(['plan' => 'pro']);
        $this->postBranch('Một')->assertCreated();
        $this->postBranch('Hai')->assertCreated();
        $tenant->update(['plan' => 'free']);

        $this->assertSame(2, Branch::query()->count());
        $this->postBranch('Ba')->assertUnprocessable()->assertJsonValidationErrors('plan');
    }

    private function registerAndLogin(string $email, string $slug): void
    {
        $this->postJson('/api/v1/auth/register', [
            'business_name' => 'Spa',
            'slug' => $slug,
            'name' => 'Owner',
            'email' => $email,
            'password' => 'password',
        ])->assertCreated();
        $this->verifyRegisteredUsers();

        $this->withHeader('Referer', 'http://localhost:5173')->get('/sanctum/csrf-cookie');
        $this->withHeaders([
            'Referer' => 'http://localhost:5173',
            'X-XSRF-TOKEN' => csrf_token(),
        ])->postJson('/api/v1/auth/login', [
            'email' => $email,
            'password' => 'password',
        ])->assertOk();
    }

    private function postBranch(string $name): TestResponse
    {
        return $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/branches', [
                'name' => $name,
                'timezone' => 'Asia/Ho_Chi_Minh',
            ]);
    }

    private function postService(): TestResponse
    {
        return $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/services', [
                'name' => 'Cắt tóc',
                'duration_minutes' => 30,
                'price_amount' => 150000,
            ]);
    }

    private function postStaff(User $user, string $branchId, string $serviceId): TestResponse
    {
        return $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/staff', [
                'user_id' => $user->public_id,
                'branch_ids' => [$branchId],
                'service_ids' => [$serviceId],
            ]);
    }

    private function staffUser(string $email, Tenant $tenant): User
    {
        return User::factory()->create([
            'email' => $email,
            'password' => 'password',
            'tenant_id' => $tenant->id,
        ]);
    }

    private function logout(): void
    {
        $this->withHeaders([
            'Referer' => 'http://localhost:5173',
            'X-XSRF-TOKEN' => csrf_token(),
        ])->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        app(TenantContext::class)->forget();
    }
}
