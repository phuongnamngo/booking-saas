<?php

namespace Tests\Feature\Booking;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceIssuer;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_reads_the_snapshot_and_another_staff_member_is_forbidden(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->fail('Overlap tests require MySQL.');
        }

        [$appointment, $customer] = $this->paidAppointment();
        $invoice = app(InvoiceIssuer::class)->issue(
            $appointment->payments()->firstOrFail(),
        );

        $this->login('owner@example.com');
        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/appointments/'.$appointment->public_id.'/invoices')
            ->assertOk()
            ->assertJsonPath('0.id', $invoice->public_id)
            ->assertJsonPath('0.customer_name', 'Khách A')
            ->assertJsonPath('0.amount', 150000)
            ->assertJsonPath('0.lines.0.type', 'charge')
            ->assertJsonPath('0.lines.0.name', 'Cắt tóc')
            ->assertJsonMissing(['type' => 'refund']);

        $customer->update(['name' => 'Khách B']);
        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/appointments/'.$appointment->public_id.'/invoices')
            ->assertOk()
            ->assertJsonPath('0.customer_name', 'Khách A');

        $invoice->update([
            'refunded_amount' => 150000,
            'refunded_at' => now(),
        ]);
        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/appointments/'.$appointment->public_id.'/invoices')
            ->assertOk()
            ->assertJsonPath('0.lines.0.type', 'charge')
            ->assertJsonPath('0.lines.0.name', 'Cắt tóc')
            ->assertJsonPath('0.lines.1.type', 'refund')
            ->assertJsonPath('0.lines.1.amount', 150000);

        $this->logout();
        $this->login('other-staff@example.com');
        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/appointments/'.$appointment->public_id.'/invoices')
            ->assertForbidden();
    }

    /**
     * @return array{0: Appointment, 1: Customer}
     */
    private function paidAppointment(): array
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
        setPermissionsTeamId($tenant->id);
        $branch = Branch::query()->create([
            'name' => 'Chi nhánh 1',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'is_active' => true,
        ]);
        $ownerStaffUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'desk-staff@example.com',
        ]);
        $staff = StaffProfile::query()->create(['user_id' => $ownerStaffUser->id]);
        $other = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'other-staff@example.com',
            'password' => 'password',
        ]);
        $other->assignRole('staff');
        StaffProfile::query()->create(['user_id' => $other->id]);
        $customer = Customer::query()->create([
            'name' => 'Khách A',
            'phone' => '0901111222',
        ]);
        $appointment = Appointment::query()->create([
            'branch_id' => $branch->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'starts_at' => CarbonImmutable::parse('2026-10-26 09:00:00', 'Asia/Ho_Chi_Minh'),
            'ends_at' => CarbonImmutable::parse('2026-10-26 09:30:00', 'Asia/Ho_Chi_Minh'),
            'status' => 'pending',
        ]);
        $service = Service::query()->create([
            'name' => 'Cắt tóc',
            'duration_minutes' => 30,
            'price_amount' => 150000,
            'is_active' => true,
        ]);
        AppointmentService::query()->create([
            'appointment_id' => $appointment->id,
            'service_id' => $service->id,
            'position' => 1,
            'duration_minutes' => 30,
            'price_amount' => 150000,
        ]);
        Payment::query()->create([
            'appointment_id' => $appointment->id,
            'amount' => 150000,
            'currency' => 'vnd',
            'status' => 'succeeded',
        ]);

        return [$appointment, $customer];
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
