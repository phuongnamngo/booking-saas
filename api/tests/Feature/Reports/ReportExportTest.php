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
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_export_becomes_a_csv_with_net_revenue(): void
    {
        [$branch, $service, $staff] = $this->workspace();
        $paid = $this->appointment($branch, $staff, $service);
        $payment = Payment::query()->create([
            'appointment_id' => $paid->id,
            'amount' => 150000,
            'currency' => 'vnd',
            'status' => 'succeeded',
            'payment_intent_id' => 'pi_export',
        ]);
        Refund::query()->create([
            'payment_id' => $payment->id,
            'amount' => 50000,
            'status' => 'succeeded',
        ]);

        $this->login('owner@example.com');
        $created = $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/reports/exports', [
                'from' => '2026-10-26',
                'to' => '2026-10-26',
                'branch_id' => $branch->public_id,
                'group' => 'day',
            ])
            ->assertStatus(202);

        $id = $created->json('id');
        $this->assertNotEmpty($id);

        $csv = $this->withHeader('Referer', 'http://localhost:5173')
            ->get('/api/v1/reports/exports/'.$id);
        $csv->assertOk();
        $csv->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $body = $csv->getContent();
        $this->assertStringContainsString('period,revenue,cancel_rate,no_show_rate', $body);
        $this->assertStringContainsString('100000', $body);

        $this->logout();
        $this->login('staff@example.com');
        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/reports/exports', [
                'from' => '2026-10-26',
                'to' => '2026-10-26',
                'branch_id' => $branch->public_id,
            ])
            ->assertForbidden();

        $this->postJson('/api/v1/auth/register', [
            'business_name' => 'Spa B',
            'slug' => 'spa-b',
            'name' => 'Owner B',
            'email' => 'owner-b@example.com',
            'password' => 'password',
        ])->assertCreated();
        $this->logout();
        $this->login('owner-b@example.com');
        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/reports/exports/'.$id)
            ->assertNotFound();
    }

    /**
     * @return array{0: Branch, 1: Service, 2: StaffProfile}
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
            'name' => 'Chi nhánh 1',
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

        return [$branch, $service, $staff];
    }

    private function appointment(Branch $branch, StaffProfile $staff, Service $service): Appointment
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
            'status' => 'pending',
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
