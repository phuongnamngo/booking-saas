<?php

namespace Tests\Feature\Booking;

use App\Mail\ReviewRequestMail;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Review;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use RefreshDatabase;

    private string $token = '';

    public function test_completed_appointment_sends_a_one_time_review_link(): void
    {
        Mail::fake();
        $appointment = $this->appointment('khach@example.com');
        $appointment->update(['status' => 'in_progress']);

        $this->login('owner@example.com');
        $response = $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/appointments/'.$appointment->public_id.'/status', [
                'status' => 'completed',
            ])
            ->assertOk();

        Mail::assertQueued(ReviewRequestMail::class, function (ReviewRequestMail $mail): bool {
            $this->token = $mail->token;

            return $mail->appointment->is($this->appointmentByToken($mail));
        });
        $this->assertNotSame('', $this->token);
        $this->assertStringNotContainsString($this->token, $response->getContent());

        $appointment->update(['status' => 'confirmed']);
        $this->postJson('/api/v1/public/spa-a/reviews', [
            'token' => $this->token,
            'rating' => 5,
        ])->assertUnprocessable();

        $appointment->update(['status' => 'completed']);
        Review::query()->where('appointment_id', $appointment->id)->update([
            'expires_at' => now()->subDay(),
        ]);
        $this->postJson('/api/v1/public/spa-a/reviews', [
            'token' => $this->token,
            'rating' => 5,
        ])->assertUnprocessable();

        Review::query()->where('appointment_id', $appointment->id)->update([
            'expires_at' => now()->addDays(14),
        ]);
        $this->postJson('/api/v1/public/spa-a/reviews', [
            'token' => $this->token,
            'rating' => 5,
            'comment' => 'Tốt',
        ])->assertCreated();
        $this->assertSame(1, Review::query()->where('appointment_id', $appointment->id)->where('rating', 5)->count());

        $this->postJson('/api/v1/public/spa-a/reviews', [
            'token' => $this->token,
            'rating' => 4,
        ])->assertUnprocessable();
        $this->assertSame(1, Review::query()->count());
    }

    public function test_customer_without_email_does_not_get_a_review_mail(): void
    {
        Mail::fake();
        $appointment = $this->appointment(null);
        $appointment->update(['status' => 'in_progress']);

        $this->login('owner@example.com');
        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/appointments/'.$appointment->public_id.'/status', [
                'status' => 'completed',
            ])
            ->assertOk();

        Mail::assertNotQueued(ReviewRequestMail::class);
    }

    private function appointmentByToken(ReviewRequestMail $mail): Appointment
    {
        return $mail->appointment;
    }

    private function appointment(?string $email): Appointment
    {
        $this->seed(PermissionSeeder::class);
        if (Tenant::query()->where('slug', 'spa-a')->doesntExist()) {
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
        $branch = Branch::query()->first() ?? Branch::query()->create([
            'name' => 'Chi nhánh 1',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'is_active' => true,
        ]);
        $user = User::query()->where('email', 'staff@example.com')->first() ?? User::factory()->create([
            'email' => 'staff@example.com',
            'password' => 'password',
            'tenant_id' => $tenant->id,
        ]);
        $staff = StaffProfile::query()->first() ?? StaffProfile::query()->create(['user_id' => $user->id]);
        $customer = Customer::query()->create([
            'name' => 'Khách',
            'phone' => '090'.random_int(1000000, 9999999),
            'email' => $email,
        ]);

        return Appointment::query()->create([
            'branch_id' => $branch->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'starts_at' => CarbonImmutable::parse('2026-10-26 09:00:00', 'Asia/Ho_Chi_Minh'),
            'ends_at' => CarbonImmutable::parse('2026-10-26 09:30:00', 'Asia/Ho_Chi_Minh'),
            'status' => 'pending',
        ]);
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
