<?php

namespace Tests\Feature\Booking;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchOpeningHour;
use App\Models\Payment;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWorkingHour;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PaymentGateway;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_reuses_an_open_session_and_rejects_a_paid_appointment(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->fail('Overlap tests require MySQL.');
        }

        $gateway = new class implements PaymentGateway
        {
            public int $calls = 0;

            public function createCheckout(int $amount, array $metadata): array
            {
                $this->calls++;

                return [
                    'id' => 'cs_test',
                    'url' => 'https://checkout.test/session',
                    'payment_intent_id' => 'pi_test',
                    'expires_at' => now()->addHour()->toIso8601String(),
                ];
            }

            public function refund(string $paymentIntentId, int $amount): string
            {
                return 're_test';
            }
        };
        $this->app->instance(PaymentGateway::class, $gateway);

        $tenant = Tenant::factory()->create(['slug' => 'spa-a']);
        Tenant::factory()->create(['slug' => 'spa-b']);
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
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'staff@example.com']);
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

        $created = $this->postJson('/api/v1/public/spa-a/appointments', [
            'branch_id' => $branch->public_id,
            'staff_id' => $staff->public_id,
            'service_ids' => [$service->public_id],
            'starts_at' => '2026-10-26T02:00:00+00:00',
            'customer' => ['name' => 'Khách A', 'phone' => '0901111222'],
        ])->assertCreated();

        $this->postJson('/api/v1/public/spa-a/appointments/'.$created->json('id').'/checkout')
            ->assertOk()
            ->assertJsonPath('url', 'https://checkout.test/session');
        $this->postJson('/api/v1/public/spa-a/appointments/'.$created->json('id').'/checkout')
            ->assertOk()
            ->assertJsonPath('url', 'https://checkout.test/session');
        $this->assertSame(1, $gateway->calls);
        $this->assertSame(150000, Payment::query()->firstOrFail()->amount);

        $this->postJson('/api/v1/public/spa-b/appointments/'.$created->json('id').'/checkout')
            ->assertNotFound();

        app(TenantContext::class)->set($tenant);
        Payment::query()->update(['status' => 'succeeded']);
        $this->postJson('/api/v1/public/spa-a/appointments/'.$created->json('id').'/checkout')
            ->assertStatus(422);
        $this->assertSame('pending', Appointment::query()->firstOrFail()->status);
    }
}
