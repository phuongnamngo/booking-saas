<?php

namespace Tests\Feature\Booking;

use App\Jobs\NotifyWaitlist;
use App\Mail\AppointmentCancelledMail;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MarkNoShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_confirmed_appointments_become_no_shows_without_a_refund(): void
    {
        $tenant = Tenant::factory()->create();
        app(TenantContext::class)->set($tenant);
        $branch = Branch::query()->create([
            'name' => 'Chi nhánh',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'is_active' => true,
        ]);
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $staff = StaffProfile::query()->create(['user_id' => $user->id]);
        $customer = Customer::query()->create([
            'name' => 'Khách',
            'phone' => '0901111222',
        ]);

        $overdue = $this->appointment($branch, $staff, $customer, 'confirmed', CarbonImmutable::now('UTC')->subMinutes(16));
        Payment::query()->create([
            'appointment_id' => $overdue->id,
            'amount' => 150000,
            'currency' => 'vnd',
            'status' => 'succeeded',
            'payment_intent_id' => 'pi_noshow',
        ]);
        $pending = $this->appointment($branch, $staff, $customer, 'pending', CarbonImmutable::now('UTC')->subMinutes(16));
        $inProgress = $this->appointment($branch, $staff, $customer, 'in_progress', CarbonImmutable::now('UTC')->subMinutes(16));
        $recent = $this->appointment($branch, $staff, $customer, 'confirmed', CarbonImmutable::now('UTC')->subMinutes(10));
        $upcoming = $this->appointment($branch, $staff, $customer, 'confirmed', CarbonImmutable::now('UTC')->addMinutes(10));

        app(TenantContext::class)->forget();
        Bus::fake();
        Mail::fake();

        $this->artisan('appointments:mark-no-shows')->assertSuccessful();

        $this->assertSame('no_show', $overdue->fresh()->status);
        $this->assertSame('pending', $pending->fresh()->status);
        $this->assertSame('in_progress', $inProgress->fresh()->status);
        $this->assertSame('confirmed', $recent->fresh()->status);
        $this->assertSame('confirmed', $upcoming->fresh()->status);
        $this->assertSame(0, Refund::query()->count());
        $this->assertSame('succeeded', Payment::query()->firstOrFail()->status);

        $audit = AuditLog::withoutGlobalScope('tenant')->where('action', 'appointment.no_show')->firstOrFail();
        $this->assertNull($audit->actor_user_id);
        $this->assertSame($tenant->id, $audit->tenant_id);
        Bus::assertNotDispatched(NotifyWaitlist::class);
        Mail::assertNotQueued(AppointmentCancelledMail::class);
    }

    public function test_no_show_command_runs_every_fifteen_minutes(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event): bool => str_contains((string) $event->command, 'appointments:mark-no-shows'));

        $this->assertNotNull($event);
        $this->assertSame('*/15 * * * *', $event->expression);
    }

    private function appointment(Branch $branch, StaffProfile $staff, Customer $customer, string $status, CarbonImmutable $startsAt): Appointment
    {
        return Appointment::query()->create([
            'branch_id' => $branch->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes(30),
            'status' => $status,
        ]);
    }
}
