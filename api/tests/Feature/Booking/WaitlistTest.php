<?php

namespace Tests\Feature\Booking;

use App\Mail\WaitlistOpenedMail;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchOpeningHour;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWorkingHour;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Support\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class WaitlistTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_a_slot_emails_the_oldest_waiting_customer_once(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->fail('Overlap tests require MySQL.');
        }

        [$branch, $staff, $service] = $this->workspace();
        $payload = [
            'branch_id' => $branch->public_id,
            'staff_id' => $staff->public_id,
            'service_ids' => [$service->public_id],
            'starts_at' => '2026-10-26T02:00:00+00:00',
            'customer' => [
                'name' => 'Khách chờ',
                'phone' => '0902222333',
            ],
        ];

        $this->postJson('/api/v1/public/spa-a/waitlist', $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cần email để báo khi có giờ.');

        $payload['customer']['email'] = 'cho@example.com';
        $this->postJson('/api/v1/public/spa-a/waitlist', $payload)->assertCreated();
        $this->assertSame(1, WaitlistEntry::query()->count());

        $this->postJson('/api/v1/public/spa-a/appointments', [
            'branch_id' => $branch->public_id,
            'staff_id' => $staff->public_id,
            'service_ids' => [$service->public_id],
            'starts_at' => '2026-10-26T02:00:00+00:00',
            'customer' => [
                'name' => 'Khách đặt',
                'phone' => '0903333444',
            ],
        ])->assertCreated();

        $appointment = Appointment::query()->firstOrFail();
        $before = Appointment::query()->count();
        Mail::fake();
        $this->login('owner@example.com');
        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/appointments/'.$appointment->public_id.'/cancel')
            ->assertOk();

        $this->assertSame($before, Appointment::query()->count());
        Mail::assertQueued(WaitlistOpenedMail::class);
        $this->assertNotNull(WaitlistEntry::query()->firstOrFail()->notified_at);

        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/appointments/'.$appointment->public_id.'/cancel')
            ->assertUnprocessable();
        Mail::assertQueued(WaitlistOpenedMail::class, 1);
    }

    /**
     * @return array{0: Branch, 1: StaffProfile, 2: Service}
     */
    private function workspace(): array
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

        return [$branch, $staff, $service];
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
