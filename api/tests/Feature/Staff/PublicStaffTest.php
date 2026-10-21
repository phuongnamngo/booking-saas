<?php

namespace Tests\Feature\Staff;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicStaffTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_staff_must_cover_every_requested_service(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->postJson('/api/v1/auth/register', [
            'business_name' => 'Spa A',
            'slug' => 'spa-a',
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'password',
        ])->assertCreated();

        $this->withHeader('Referer', 'http://localhost:5173')->get('/sanctum/csrf-cookie');
        $this->withHeaders([
            'Referer' => 'http://localhost:5173',
            'X-XSRF-TOKEN' => csrf_token(),
        ])->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'password',
        ])->assertOk();

        $branchId = $this->withHeader('Referer', 'http://localhost:5173')->postJson('/api/v1/branches', [
            'name' => 'Chi nhánh 1',
            'timezone' => 'Asia/Ho_Chi_Minh',
        ])->assertCreated()->json('id');

        $cutId = $this->withHeader('Referer', 'http://localhost:5173')->postJson('/api/v1/services', [
            'name' => 'Cắt',
            'duration_minutes' => 30,
            'price_amount' => 100000,
        ])->assertCreated()->json('id');

        $colorId = $this->withHeader('Referer', 'http://localhost:5173')->postJson('/api/v1/services', [
            'name' => 'Nhuộm',
            'duration_minutes' => 45,
            'price_amount' => 200000,
        ])->assertCreated()->json('id');

        $tenant = Tenant::query()->where('slug', 'spa-a')->firstOrFail();
        app(TenantContext::class)->set($tenant);
        setPermissionsTeamId($tenant->id);

        $full = $this->staffUser('full@example.com', $tenant->id);
        $partial = $this->staffUser('partial@example.com', $tenant->id);

        $fullId = $this->withHeader('Referer', 'http://localhost:5173')->postJson('/api/v1/staff', [
            'user_id' => $full->public_id,
            'branch_ids' => [$branchId],
            'service_ids' => [$cutId, $colorId],
        ])->assertCreated()->json('id');

        $this->withHeader('Referer', 'http://localhost:5173')->postJson('/api/v1/staff', [
            'user_id' => $partial->public_id,
            'branch_ids' => [$branchId],
            'service_ids' => [$cutId],
        ])->assertCreated();

        $staff = $this->getJson('/api/v1/public/spa-a/staff?'.http_build_query([
            'branch_id' => $branchId,
            'service_ids' => [$cutId, $colorId],
        ]))->assertOk()->json();

        $this->assertSame([$fullId], array_column($staff, 'id'));
    }

    private function staffUser(string $email, int $tenantId): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => 'password',
            'tenant_id' => $tenantId,
        ]);
        $user->assignRole('staff');

        return $user;
    }
}
