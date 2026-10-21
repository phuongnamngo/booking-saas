<?php

namespace Tests\Feature\Booking;

use App\Mail\AppointmentReminderMail;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AppointmentReminderTest extends TestCase
{
    use RefreshDatabase;

    public function test_24h_window_queues_one_mail_and_skips_the_second_run(): void
    {
        $appointment = $this->appointment(now()->addHours(24), 'khach@example.com');
        Mail::fake();

        $this->artisan('appointments:remind')->assertSuccessful();
        $this->artisan('appointments:remind')->assertSuccessful();

        Mail::assertQueued(AppointmentReminderMail::class, 1);
        Mail::assertQueued(AppointmentReminderMail::class, function (AppointmentReminderMail $mail): bool {
            return $mail->hasTo('khach@example.com') && $mail->hasTo('staff@example.com');
        });
        $this->assertNotNull($appointment->refresh()->reminder_24h_sent_at);
    }

    public function test_appointment_ten_hours_away_is_not_reminded(): void
    {
        $this->appointment(now()->addHours(10), 'khach@example.com');
        Mail::fake();

        $this->artisan('appointments:remind')->assertSuccessful();

        Mail::assertNotQueued(AppointmentReminderMail::class);
    }

    public function test_2h_window_queues_once_and_missing_customer_email_still_reaches_staff(): void
    {
        $appointment = $this->appointment(now()->addHours(2), null, 'confirmed');
        Mail::fake();

        $this->artisan('appointments:remind')->assertSuccessful();
        $this->artisan('appointments:remind')->assertSuccessful();

        Mail::assertQueued(AppointmentReminderMail::class, 1);
        Mail::assertNotQueued(AppointmentReminderMail::class, fn (AppointmentReminderMail $mail): bool => $mail->hasTo('khach@example.com'));
        Mail::assertQueued(AppointmentReminderMail::class, fn (AppointmentReminderMail $mail): bool => $mail->hasTo('staff@example.com'));
        $appointment->refresh();
        $this->assertNotNull($appointment->reminder_2h_sent_at);
        $this->assertNull($appointment->reminder_24h_sent_at);
    }

    private function appointment(mixed $startsAt, ?string $email, string $status = 'pending'): Appointment
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
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'email' => 'staff@example.com',
        ]);
        $staff = StaffProfile::query()->create(['user_id' => $user->id]);
        $customer = Customer::query()->create([
            'name' => 'Khách A',
            'phone' => '0901111222',
            'email' => $email,
        ]);

        return Appointment::query()->create([
            'branch_id' => $branch->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes(30),
            'status' => $status,
        ]);
    }
}
