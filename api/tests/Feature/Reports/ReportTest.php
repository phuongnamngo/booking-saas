<?php

namespace Tests\Feature\Reports;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_revenue_is_succeeded_payments_minus_succeeded_refunds(): void
    {
        [$branch, $other, $service, $staff] = $this->workspace();
        $paid = $this->appointment($branch, $staff, $service, 'pending');
        $payment = Payment::query()->create([
            'appointment_id' => $paid->id,
            'amount' => 150000,
            'currency' => 'vnd',
            'status' => 'succeeded',
            'payment_intent_id' => 'pi_report',
        ]);
        Refund::query()->create([
            'payment_id' => $payment->id,
            'amount' => 50000,
            'status' => 'succeeded',
        ]);
        $unpaid = $this->appointment($branch, $staff, $service, 'pending');
        AppointmentService::query()->where('appointment_id', $unpaid->id)->update(['price_amount' => 300000]);

        $elsewhere = $this->appointment($other, $staff, $service, 'pending');
        Payment::query()->create([
            'appointment_id' => $elsewhere->id,
            'amount' => 70000,
            'currency' => 'vnd',
            'status' => 'succeeded',
            'payment_intent_id' => 'pi_other',
        ]);

        $this->login('owner@example.com');
        $owner = User::query()->where('email', 'owner@example.com')->firstOrFail();
        $tenant = Tenant::query()->where('slug', 'spa-a')->firstOrFail();

        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/reports?from=2026-10-26&to=2026-10-26&branch_id='.$branch->public_id)
            ->assertOk()
            ->assertJsonPath('revenue', 100000)
            ->assertJsonPath('cancel_rate', 0)
            ->assertJsonPath('no_show_rate', 0)
            ->assertJsonPath('peak_hours.0.hour', '09')
            ->assertJsonPath('peak_hours.0.count', 2)
            ->assertJsonPath('by_period.0.period', '2026-10-26')
            ->assertJsonPath('by_period.0.revenue', 100000)
            ->assertJsonPath('by_service.0.id', $service->public_id)
            ->assertJsonPath('by_service.0.name', 'Cắt tóc')
            ->assertJsonPath('by_service.0.revenue', 100000)
            ->assertJsonPath('by_staff.0.id', $staff->public_id)
            ->assertJsonPath('by_staff.0.name', 'Lan')
            ->assertJsonPath('by_staff.0.revenue', 100000);

        $this->assertTrue(Cache::has(sprintf(
            'reports:%d:%d:%s:%s:%s:%s',
            $tenant->id,
            $owner->id,
            '2026-10-26',
            '2026-10-26',
            $branch->public_id,
            'day',
        )));

        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/reports?from=2026-10-26&to=2026-10-26')
            ->assertOk()
            ->assertJsonPath('revenue', 170000);

        $this->logout();
        $this->login('staff@example.com');
        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/reports?from=2026-10-26&to=2026-10-26&branch_id='.$branch->public_id)
            ->assertForbidden();

        $this->logout();
        $this->login('manager@example.com');
        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/reports?from=2026-10-26&to=2026-10-26&branch_id='.$other->public_id)
            ->assertForbidden();
        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/reports?from=2026-10-26&to=2026-10-26')
            ->assertOk()
            ->assertJsonPath('revenue', 100000);
    }

    /**
     * @return array{0: Branch, 1: Branch, 2: Service, 3: StaffProfile}
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

        $tenant = Tenant::query()->where('slug', 'spa-a')->firstOrFail();
        app(TenantContext::class)->set($tenant);
        setPermissionsTeamId($tenant->id);

        $branch = Branch::query()->create([
            'name' => 'Chi nhánh được gán',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'is_active' => true,
        ]);
        $other = Branch::query()->create([
            'name' => 'Chi nhánh khác',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'is_active' => true,
        ]);
        $service = Service::query()->create([
            'name' => 'Cắt tóc',
            'duration_minutes' => 30,
            'price_amount' => 150000,
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'name' => 'Lan',
            'email' => 'staff@example.com',
            'password' => 'password',
            'tenant_id' => $tenant->id,
        ]);
        $user->assignRole('staff');
        $staff = StaffProfile::query()->create(['user_id' => $user->id]);

        $manager = User::factory()->create([
            'name' => 'Quản lý',
            'email' => 'manager@example.com',
            'password' => 'password',
            'tenant_id' => $tenant->id,
        ]);
        $manager->assignRole('manager');
        $manager->branches()->attach($branch->id, ['tenant_id' => $tenant->id]);

        return [$branch, $other, $service, $staff];
    }

    private function appointment(Branch $branch, StaffProfile $staff, Service $service, string $status): Appointment
    {
        app(TenantContext::class)->set($branch->tenant);
        $customer = Customer::query()->create([
            'name' => 'Khách',
            'phone' => '090'.random_int(1000000, 9999999),
        ]);
        $appointment = Appointment::query()->create([
            'branch_id' => $branch->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'starts_at' => CarbonImmutable::parse('2026-10-26 09:00:00', 'Asia/Ho_Chi_Minh'),
            'ends_at' => CarbonImmutable::parse('2026-10-26 09:30:00', 'Asia/Ho_Chi_Minh'),
            'status' => $status,
        ]);
        AppointmentService::query()->create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'position' => 1,
            'duration_minutes' => 30,
            'price_amount' => 150000,
        ]);

        return $appointment;
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

    private function logout(): void
    {
        $this->withHeaders([
            'Referer' => 'http://localhost:5173',
            'X-XSRF-TOKEN' => csrf_token(),
        ])->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
    }
}
