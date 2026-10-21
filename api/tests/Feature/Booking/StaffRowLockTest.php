<?php

namespace Tests\Feature\Booking;

use App\Models\Appointment;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StaffRowLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_second_connection_waits_and_does_not_insert(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->fail('Overlap tests require MySQL.');
        }

        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $staff = StaffProfile::query()->create(['user_id' => $user->id]);

        DB::beginTransaction();
        StaffProfile::query()->whereKey($staff->id)->lockForUpdate()->first();

        DB::connection('mysql_lock')->statement('SET innodb_lock_wait_timeout = 1');

        try {
            StaffProfile::on('mysql_lock')->whereKey($staff->id)->lockForUpdate()->first();
            $this->fail('The second connection should hit a lock wait timeout.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Lock wait timeout', $exception->getMessage());
        }

        $this->assertSame(0, Appointment::query()->count());
        DB::rollBack();
    }
}
