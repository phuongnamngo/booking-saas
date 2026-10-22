<?php

namespace Tests\Feature\Catalog;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_writes_are_audited_without_passwords(): void
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
        $this->login('owner@example.com');

        $branchId = $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/branches', [
                'name' => 'Chi nhánh 1',
                'timezone' => 'Asia/Ho_Chi_Minh',
            ])
            ->assertCreated()
            ->json('id');

        $this->assertSame(1, AuditLog::query()->where('action', 'branch.saved')->count());

        $this->withHeader('Referer', 'http://localhost:5173')
            ->putJson('/api/v1/branches/'.$branchId.'/opening-hours', [
                'intervals' => [
                    ['weekday' => 1, 'opens_at' => '09:00', 'closes_at' => '12:00'],
                ],
            ])
            ->assertOk();
        $hours = AuditLog::query()->where('action', 'opening_hours.replaced')->firstOrFail();
        $this->assertSame($branchId, $hours->subject_public_id);
        $this->assertArrayNotHasKey('password', $hours->before ?? []);
        $this->assertArrayNotHasKey('password', $hours->after ?? []);

        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/branches/'.$branchId.'/holidays', [
                'date' => '2026-10-27',
                'name' => 'Nghỉ',
            ])
            ->assertCreated();

        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/service-categories', ['name' => 'Tóc'])
            ->assertCreated();

        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/services', [
                'name' => 'Cắt tóc',
                'duration_minutes' => 30,
                'price_amount' => 150000,
            ])
            ->assertCreated();

        $tenant = Tenant::query()->where('slug', 'spa-a')->firstOrFail();
        app(TenantContext::class)->set($tenant);
        setPermissionsTeamId($tenant->id);
        $staffUser = User::factory()->create([
            'email' => 'staff@example.com',
            'password' => 'password',
            'tenant_id' => $tenant->id,
        ]);
        $staffId = $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/staff', [
                'user_id' => $staffUser->public_id,
                'branch_ids' => [$branchId],
                'service_ids' => [],
            ])
            ->assertCreated()
            ->json('id');

        $this->withHeader('Referer', 'http://localhost:5173')
            ->putJson('/api/v1/staff/'.$staffId.'/working-hours', [
                'intervals' => [
                    ['weekday' => 1, 'starts_at' => '09:00', 'ends_at' => '12:00'],
                ],
            ])
            ->assertOk();

        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/staff/'.$staffId.'/exceptions', [
                'type' => 'off',
                'starts_at' => '2026-10-27T09:00:00+07:00',
                'ends_at' => '2026-10-27T12:00:00+07:00',
            ])
            ->assertCreated();

        foreach ([
            'holiday.created',
            'service_category.saved',
            'service.saved',
            'staff.saved',
            'working_hours.replaced',
            'exception.created',
        ] as $action) {
            $this->assertSame(1, AuditLog::query()->where('action', $action)->count(), $action);
        }

        $hoursLog = AuditLog::query()->where('action', 'working_hours.replaced')->firstOrFail();
        $this->assertSame($staffId, $hoursLog->subject_public_id);

        foreach (AuditLog::query()->get() as $log) {
            $encoded = json_encode($log->only(['before', 'after']));
            $this->assertIsString($encoded);
            $this->assertStringNotContainsString('password', $encoded);
            $this->assertStringNotContainsString('"token"', $encoded);
        }
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
