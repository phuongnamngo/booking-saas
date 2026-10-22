<?php

namespace Tests\Feature\Catalog;

use App\Models\Branch;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BranchScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_weekday_can_have_two_intervals_and_a_holiday(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'business_name' => 'Spa A',
            'slug' => 'spa-a',
            'name' => 'Spa A',
            'email' => 'a@example.com',
            'password' => 'password',
        ])->assertCreated();
        $this->verifyRegisteredUsers();

        $this->withHeader('Referer', 'http://localhost:5173')->get('/sanctum/csrf-cookie');
        $this->withHeaders([
            'Referer' => 'http://localhost:5173',
            'X-XSRF-TOKEN' => csrf_token(),
        ])->postJson('/api/v1/auth/login', [
            'email' => 'a@example.com',
            'password' => 'password',
        ])->assertOk();

        $branchId = $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/branches', [
                'name' => 'Chi nhánh 1',
                'timezone' => 'Asia/Ho_Chi_Minh',
            ])
            ->assertCreated()
            ->json('id');

        $this->withHeader('Referer', 'http://localhost:5173')
            ->putJson('/api/v1/branches/'.$branchId.'/opening-hours', [
                'intervals' => [
                    ['weekday' => 1, 'opens_at' => '09:00', 'closes_at' => '12:00'],
                    ['weekday' => 1, 'opens_at' => '13:00', 'closes_at' => '18:00'],
                ],
            ])
            ->assertOk();

        $branch = Branch::query()->where('public_id', $branchId)->firstOrFail();

        $this->assertSame(2, DB::table('branch_opening_hours')->where('branch_id', $branch->id)->count());

        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/branches/'.$branchId.'/holidays', [
                'date' => '2026-12-25',
                'name' => 'Giáng sinh',
            ])
            ->assertCreated();

        $this->assertSame(1, DB::table('branch_holidays')->where('branch_id', $branch->id)->count());
    }
}
