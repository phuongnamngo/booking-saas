<?php

namespace Tests\Feature\Booking;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceIssuer;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceIssuerTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_snapshots_one_invoice_and_keeps_the_customer_name(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->fail('Overlap tests require MySQL.');
        }

        [$payment, $customer] = $this->paidAppointment('Khách A');
        $issuer = app(InvoiceIssuer::class);
        $invoice = $issuer->issue($payment->fresh());
        $issuer->issue($payment->fresh());

        $this->assertSame(1, Invoice::query()->count());
        $this->assertSame(150000, $invoice->amount);
        $this->assertSame('Khách A', $invoice->customer_name);
        $this->assertSame([
            ['name' => 'Cắt tóc', 'amount' => 100000],
            ['name' => 'Gội', 'amount' => 80000],
        ], $invoice->lines);

        $customer->update(['name' => 'Khách B']);
        $issuer->issue($payment->fresh());
        $this->assertSame('Khách A', $invoice->fresh()->customer_name);

        $bare = Payment::query()->create([
            'appointment_id' => $payment->appointment_id,
            'amount' => 1000,
            'currency' => 'vnd',
            'status' => 'succeeded',
        ]);
        $issuer->markRefunded($bare, new Refund(['amount' => 1000]));
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_issue_uses_an_empty_customer_name_when_the_customer_name_is_empty(): void
    {
        [$payment] = $this->paidAppointment('');
        $invoice = app(InvoiceIssuer::class)->issue($payment);

        $this->assertSame('', $invoice->customer_name);
    }

    public function test_mark_refunded_records_the_refund_on_the_existing_invoice(): void
    {
        [$payment] = $this->paidAppointment('Khách A');
        $issuer = app(InvoiceIssuer::class);
        $invoice = $issuer->issue($payment);
        $refund = Refund::query()->create([
            'payment_id' => $payment->id,
            'amount' => 150000,
            'status' => 'succeeded',
            'gateway_refund_id' => 're_test',
        ]);

        $issuer->markRefunded($payment, $refund);

        $invoice->refresh();
        $this->assertSame(150000, $invoice->refunded_amount);
        $this->assertNotNull($invoice->refunded_at);
    }

    /**
     * @return array{0: Payment, 1: Customer}
     */
    private function paidAppointment(string $customerName): array
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::query()->create([
            'name' => 'Chi nhánh 1',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'is_active' => true,
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $staff = StaffProfile::query()->create(['user_id' => $user->id]);
        $customer = Customer::query()->create([
            'name' => $customerName,
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
        $cut = Service::query()->create([
            'name' => 'Cắt tóc',
            'duration_minutes' => 30,
            'price_amount' => 100000,
            'is_active' => true,
        ]);
        $wash = Service::query()->create([
            'name' => 'Gội',
            'duration_minutes' => 30,
            'price_amount' => 80000,
            'is_active' => true,
        ]);
        AppointmentService::query()->create([
            'appointment_id' => $appointment->id,
            'service_id' => $cut->id,
            'position' => 1,
            'duration_minutes' => 30,
            'price_amount' => 100000,
        ]);
        AppointmentService::query()->create([
            'appointment_id' => $appointment->id,
            'service_id' => $wash->id,
            'position' => 2,
            'duration_minutes' => 30,
            'price_amount' => 80000,
        ]);
        $payment = Payment::query()->create([
            'appointment_id' => $appointment->id,
            'amount' => 150000,
            'currency' => 'vnd',
            'status' => 'succeeded',
        ]);

        return [$payment, $customer];
    }
}
