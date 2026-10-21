<?php

namespace Tests\Feature\Booking;

use App\Mail\AppointmentCancelledMail;
use App\Mail\AppointmentConfirmedMail;
use App\Mail\AppointmentRescheduledMail;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchOpeningHour;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWorkingHour;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AppointmentMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_booking_queues_mail_to_customer_and_staff(): void
    {
        ['branch' => $branch, 'staff' => $staff, 'service' => $service] = $this->fixture();
        Mail::fake();

        $this->postJson('/api/v1/public/spa-a/appointments', $this->payload($branch, $staff, $service, 'khach@example.com'))
            ->assertCreated();

        Mail::assertQueued(AppointmentConfirmedMail::class, function (AppointmentConfirmedMail $mail): bool {
            return $mail->hasTo('khach@example.com') && $mail->hasTo('staff@example.com');
        });
    }

    public function test_guest_booking_without_email_still_queues_staff_mail(): void
    {
        ['branch' => $branch, 'staff' => $staff, 'service' => $service] = $this->fixture();
        Mail::fake();

        $this->postJson('/api/v1/public/spa-a/appointments', $this->payload($branch, $staff, $service, null))
            ->assertCreated();

        $this->assertSame(1, Appointment::query()->count());
        Mail::assertNotQueued(AppointmentConfirmedMail::class, fn (AppointmentConfirmedMail $mail): bool => $mail->hasTo('khach@example.com'));
        Mail::assertQueued(AppointmentConfirmedMail::class, fn (AppointmentConfirmedMail $mail): bool => $mail->hasTo('staff@example.com'));
    }

    public function test_reschedule_and_cancel_queue_their_own_mail(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->postJson('/api/v1/auth/register', [
            'business_name' => 'Spa A',
            'slug' => 'spa-a',
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => 'password',
        ])->assertCreated();

        ['branch' => $branch, 'staff' => $staff, 'service' => $service] = $this->fixture(registerTenant: false);
        Mail::fake();

        $created = $this->postJson('/api/v1/public/spa-a/appointments', $this->payload($branch, $staff, $service, 'khach@example.com'))
            ->assertCreated();

        $this->withHeader('Referer', 'http://localhost:5173')->get('/sanctum/csrf-cookie');
        $this->withHeaders([
            'Referer' => 'http://localhost:5173',
            'X-XSRF-TOKEN' => csrf_token(),
        ])->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->withHeader('Referer', 'http://localhost:5173')
            ->patchJson('/api/v1/appointments/'.$created->json('id'), [
                'starts_at' => '2026-10-26T02:30:00+00:00',
            ])
            ->assertOk();

        Mail::assertQueued(AppointmentRescheduledMail::class, function (AppointmentRescheduledMail $mail): bool {
            return $mail->hasTo('khach@example.com') && $mail->hasTo('staff@example.com');
        });

        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/appointments/'.$created->json('id').'/cancel')
            ->assertOk();

        Mail::assertQueued(AppointmentCancelledMail::class, function (AppointmentCancelledMail $mail): bool {
            return $mail->hasTo('khach@example.com') && $mail->hasTo('staff@example.com');
        });
    }

    /**
     * @return array{branch: Branch, staff: StaffProfile, service: Service}
     */
    private function fixture(bool $registerTenant = true): array
    {
        if (config('database.default') !== 'mysql') {
            $this->fail('Overlap tests require MySQL.');
        }

        if ($registerTenant) {
            $tenant = Tenant::factory()->create(['slug' => 'spa-a']);
        } else {
            $tenant = Tenant::query()->where('slug', 'spa-a')->firstOrFail();
        }

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

        return compact('branch', 'staff', 'service');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Branch $branch, StaffProfile $staff, Service $service, ?string $email): array
    {
        $customer = [
            'name' => 'Khách A',
            'phone' => '0901111222',
        ];

        if ($email !== null) {
            $customer['email'] = $email;
        }

        return [
            'branch_id' => $branch->public_id,
            'staff_id' => $staff->public_id,
            'service_ids' => [$service->public_id],
            'starts_at' => '2026-10-26T02:00:00+00:00',
            'customer' => $customer,
        ];
    }
}
