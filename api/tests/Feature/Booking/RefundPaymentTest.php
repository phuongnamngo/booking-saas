<?php

namespace Tests\Feature\Booking;

use App\Jobs\RefundPayment;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PaymentGateway;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class RefundPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancel_refunds_a_succeeded_payment_once(): void
    {
        [$appointment, $payment, $gateway] = $this->paidAppointment();

        $this->login('owner@example.com');
        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/appointments/'.$appointment->public_id.'/cancel')
            ->assertOk();

        $this->assertSame(1, $gateway->refunds);
        $this->assertSame(1, Refund::query()->count());
        $this->assertSame('succeeded', Refund::query()->firstOrFail()->status);
        $this->assertSame('refunded', $payment->refresh()->status);

        $payment->refresh();
        RefundPayment::dispatch((int) $payment->id, (int) $payment->tenant_id);
        $this->assertSame(1, $gateway->refunds);

        $this->withHeader('Referer', 'http://localhost:5173')
            ->getJson('/api/v1/appointments/'.$appointment->public_id.'/payments')
            ->assertOk()
            ->assertJsonPath('0.id', $payment->public_id)
            ->assertJsonPath('0.amount', 150000)
            ->assertJsonPath('0.status', 'refunded')
            ->assertJsonPath('0.refunds.0.amount', 150000)
            ->assertJsonPath('0.refunds.0.status', 'succeeded');
    }

    public function test_completed_cancel_and_no_show_do_not_refund(): void
    {
        [$appointment] = $this->paidAppointment();
        $appointment->update(['status' => 'completed']);

        $this->login('owner@example.com');
        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/appointments/'.$appointment->public_id.'/cancel')
            ->assertUnprocessable();
        $this->assertSame(0, Refund::query()->count());

        $confirmed = $this->paidAppointment()[0];
        $confirmed->update(['status' => 'confirmed']);
        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/appointments/'.$confirmed->public_id.'/status', ['status' => 'no_show'])
            ->assertOk();
        $this->assertSame(0, Refund::query()->count());
    }

    public function test_gateway_failure_leaves_the_cancel_committed_and_the_payment_pending_refund(): void
    {
        [$appointment, $payment] = $this->paidAppointment(failRefund: true);

        $this->login('owner@example.com');
        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/appointments/'.$appointment->public_id.'/cancel');

        $this->assertSame('cancelled', $appointment->refresh()->status);
        $this->assertSame('refund_pending', $payment->refresh()->status);
        $this->assertSame(0, Refund::query()->where('status', 'succeeded')->count());
    }

    /**
     * @return array{0: Appointment, 1: Payment, 2: PaymentGateway}
     */
    private function paidAppointment(bool $failRefund = false): array
    {
        if (config('database.default') !== 'mysql') {
            $this->fail('Overlap tests require MySQL.');
        }

        if (Tenant::query()->where('slug', 'spa-a')->doesntExist()) {
            $this->seed(PermissionSeeder::class);
            $this->postJson('/api/v1/auth/register', [
                'business_name' => 'Spa A',
                'slug' => 'spa-a',
                'name' => 'Owner',
                'email' => 'owner@example.com',
                'password' => 'password',
            ])->assertCreated();
        }

        $tenant = Tenant::query()->where('slug', 'spa-a')->firstOrFail();
        app(TenantContext::class)->set($tenant);
        setPermissionsTeamId($tenant->id);
        $branch = Branch::query()->create([
            'name' => 'Chi nhánh 1',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'is_active' => true,
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'staff-'.uniqid().'@example.com',
        ]);
        $staff = StaffProfile::query()->create(['user_id' => $user->id]);
        $customer = Customer::query()->create([
            'name' => 'Khách A',
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
        $payment = Payment::query()->create([
            'appointment_id' => $appointment->id,
            'amount' => 150000,
            'currency' => 'vnd',
            'status' => 'succeeded',
            'payment_intent_id' => 'pi_paid',
        ]);
        $gateway = new class($failRefund) implements PaymentGateway
        {
            public int $refunds = 0;

            public function __construct(private bool $failRefund) {}

            public function createCheckout(int $amount, array $metadata): array
            {
                return [
                    'id' => 'cs_test',
                    'url' => 'https://checkout.test/session',
                    'payment_intent_id' => 'pi_paid',
                    'expires_at' => now()->addHour()->toIso8601String(),
                ];
            }

            public function refund(string $paymentIntentId, int $amount): string
            {
                $this->refunds++;
                if ($this->failRefund) {
                    throw new RuntimeException('stripe down');
                }

                return 're_test';
            }
        };
        $this->app->instance(PaymentGateway::class, $gateway);

        return [$appointment, $payment, $gateway];
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
