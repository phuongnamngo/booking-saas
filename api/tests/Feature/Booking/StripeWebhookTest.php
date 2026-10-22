<?php

namespace Tests\Feature\Booking;

use App\Mail\PaymentReceiptMail;
use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StripeEvent;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceIssuer;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

class StripeWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_marks_one_payment_succeeded_and_rejects_a_bad_signature(): void
    {
        [$appointment, $payment] = $this->pendingPayment();
        $payload = json_encode([
            'id' => 'evt_1',
            'object' => 'event',
            'api_version' => '2026-09-30.endive',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test',
                    'object' => 'checkout.session',
                    'payment_intent' => 'pi_paid',
                    'metadata' => [
                        'appointment_public_id' => $appointment->public_id,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $this->postWebhook($payload, 't=1,v1=bad')->assertStatus(400);
        $this->assertSame('pending', $payment->refresh()->status);
        $this->assertSame(0, Invoice::query()->count());

        $this->postSigned($payload)->assertOk();
        $this->assertSame('succeeded', $payment->refresh()->status);
        $this->assertSame('pi_paid', $payment->payment_intent_id);
        $this->assertSame(1, Invoice::query()->count());
        $invoice = Invoice::query()->firstOrFail();
        $this->assertSame('Khách A', $invoice->customer_name);
        $this->assertSame(150000, $invoice->amount);
        $this->assertSame('Cắt tóc', $invoice->lines[0]['name']);

        $this->postSigned($payload)->assertOk();
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(1, Invoice::query()->count());
    }

    public function test_missing_appointment_and_a_settled_payment_do_not_create_an_invoice(): void
    {
        [$appointment, $payment] = $this->pendingPayment();
        $missing = $this->payload('evt_missing', '01ARZ3NDEKTSV4RRFFQ69G5FAV');
        $this->postSigned($missing)->assertOk();
        $this->assertSame(0, Invoice::query()->count());

        $payment->update(['status' => 'succeeded']);
        $this->postSigned($this->payload('evt_settled', $appointment->public_id))->assertOk();
        $this->assertSame(0, Invoice::query()->count());
    }

    public function test_webhook_queues_a_receipt_when_the_customer_has_an_email(): void
    {
        Mail::fake();
        [$appointment] = $this->pendingPayment();
        $appointment->customer()->update(['email' => 'khach@example.com']);

        $this->postSigned($this->payload('evt_mail', $appointment->public_id))->assertOk();

        Mail::assertQueued(PaymentReceiptMail::class, function (PaymentReceiptMail $mail): bool {
            return $mail->hasTo('khach@example.com')
                && $mail->attachments() === []
                && str_contains($mail->render(), $mail->invoice->public_id)
                && str_contains($mail->render(), 'Cắt tóc');
        });
    }

    public function test_webhook_keeps_the_invoice_without_queueing_mail_when_email_is_empty(): void
    {
        Mail::fake();
        [$appointment] = $this->pendingPayment();

        $this->postSigned($this->payload('evt_nomail', $appointment->public_id))->assertOk();

        $this->assertSame(1, Invoice::query()->count());
        Mail::assertNotQueued(PaymentReceiptMail::class);
    }

    public function test_a_failed_invoice_deletes_the_stripe_event_so_stripe_can_retry(): void
    {
        Mail::fake();
        [, $payment] = $this->pendingPayment();
        $this->mock(InvoiceIssuer::class, function ($mock): void {
            $mock->shouldReceive('issue')->once()->andThrow(new RuntimeException('invoice down'));
        });

        $this->postSigned($this->payload('evt_fail', $payment->appointment->public_id))->assertStatus(500);
        $this->assertSame('pending', $payment->refresh()->status);
        $this->assertSame(0, StripeEvent::query()->count());
        $this->assertSame(0, Invoice::query()->count());
        Mail::assertNotQueued(PaymentReceiptMail::class);
    }

    /**
     * @return array{0: Appointment, 1: Payment}
     */
    private function pendingPayment(): array
    {
        if (config('database.default') !== 'mysql') {
            $this->fail('Overlap tests require MySQL.');
        }

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
        $payment = Payment::query()->create([
            'appointment_id' => $appointment->id,
            'amount' => 150000,
            'currency' => 'vnd',
            'status' => 'pending',
        ]);

        return [$appointment, $payment];
    }

    private function payload(string $eventId, string $appointmentPublicId): string
    {
        return json_encode([
            'id' => $eventId,
            'object' => 'event',
            'api_version' => '2026-09-30.endive',
            'type' => 'checkout.session.completed',
            'data' => [
                'object' => [
                    'id' => 'cs_test',
                    'object' => 'checkout.session',
                    'payment_intent' => 'pi_paid',
                    'metadata' => [
                        'appointment_public_id' => $appointmentPublicId,
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
    }

    private function postSigned(string $payload): TestResponse
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

        return $this->postWebhook($payload, 't='.$timestamp.',v1='.$signature);
    }

    private function postWebhook(string $payload, string $signature): TestResponse
    {
        return $this->call('POST', '/api/v1/stripe/webhook', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }
}
