<?php

namespace Tests\Feature\Booking;

use App\Models\Appointment;
use App\Models\AppointmentService;
use App\Models\Branch;
use App\Models\BranchOpeningHour;
use App\Models\Customer;
use App\Models\Refund;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWorkingHour;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ManageToken;
use App\Support\TenantContext;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicManageAppointmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow('2026-10-22 09:00:00 UTC');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_guest_can_read_and_cancel_only_outside_the_24_hour_window(): void
    {
        ['branch' => $branch, 'staff' => $staff, 'service' => $service] = $this->fixture();
        $edge = $this->held($branch, $staff, $service, '2026-10-23 09:00:00 UTC');
        $token = ManageToken::issue($edge);

        $this->getJson('/api/v1/public/spa-a/appointments/'.$edge->public_id.'?token='.$token)
            ->assertOk()
            ->assertJsonPath('id', $edge->public_id)
            ->assertJsonPath('status', 'pending');

        $this->postJson('/api/v1/public/spa-a/appointments/'.$edge->public_id.'/cancel?token=wrong')
            ->assertNotFound();
        $this->getJson('/api/v1/public/spa-a/appointments/'.$edge->public_id.'?token=wrong')
            ->assertNotFound();

        $this->postJson('/api/v1/public/spa-a/appointments/'.$edge->public_id.'/cancel?token='.$token)
            ->assertUnprocessable()
            ->assertJsonPath('errors.starts_at.0', 'Chỉ đổi hoặc hủy trước 24 giờ.');
        $this->assertSame('pending', $edge->fresh()->status);

        $open = $this->held($branch, $staff, $service, '2026-10-23 09:00:01 UTC');
        $openToken = ManageToken::issue($open);
        $this->postJson('/api/v1/public/spa-a/appointments/'.$open->public_id.'/cancel?token='.$openToken)
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');
        $this->postJson('/api/v1/public/spa-a/appointments/'.$open->public_id.'/cancel?token='.$openToken)
            ->assertUnprocessable()
            ->assertJsonPath('errors.status.0', 'Không chuyển được trạng thái này.');
    }

    public function test_staff_can_cancel_and_reschedule_inside_the_window(): void
    {
        ['branch' => $branch, 'staff' => $staff, 'service' => $service] = $this->fixture();
        $this->login();

        $inside = $this->held($branch, $staff, $service, '2026-10-23 08:00:00 UTC');
        $this->withHeader('Referer', 'http://localhost:5173')
            ->postJson('/api/v1/appointments/'.$inside->public_id.'/cancel')
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');

        $created = $this->postJson('/api/v1/public/spa-a/appointments', [
            'branch_id' => $branch->public_id,
            'staff_id' => $staff->public_id,
            'service_ids' => [$service->public_id],
            'starts_at' => '2026-10-23T02:00:00+00:00',
            'customer' => ['name' => 'Khách', 'phone' => '0901111222', 'email' => 'khach@example.com'],
        ])->assertCreated();

        $this->withHeader('Referer', 'http://localhost:5173')
            ->patchJson('/api/v1/appointments/'.$created->json('id'), [
                'starts_at' => '2026-10-23T03:00:00+00:00',
            ])
            ->assertOk()
            ->assertJsonPath('starts_at', '2026-10-23T03:00:00+00:00');
    }

    public function test_public_cancel_refunds_a_succeeded_payment(): void
    {
        ['branch' => $branch, 'staff' => $staff] = $this->fixture();
        $appointment = $this->held($branch, $staff, null, '2026-10-26 02:00:00 UTC');
        $appointment->payments()->create([
            'amount' => 150000,
            'currency' => 'vnd',
            'status' => 'succeeded',
            'payment_intent_id' => 'pi_public',
        ]);
        $token = ManageToken::issue($appointment);

        $this->postJson('/api/v1/public/spa-a/appointments/'.$appointment->public_id.'/cancel?token='.$token)
            ->assertOk();

        $this->assertSame(1, Refund::query()->count());
        $this->assertSame('succeeded', Refund::query()->firstOrFail()->status);
    }

    public function test_public_reschedule_keeps_services_and_optional_staff(): void
    {
        ['branch' => $branch, 'staff' => $staff, 'service' => $service, 'other' => $other] = $this->fixture();
        $created = $this->postJson('/api/v1/public/spa-a/appointments', [
            'branch_id' => $branch->public_id,
            'staff_id' => $staff->public_id,
            'service_ids' => [$service->public_id],
            'starts_at' => '2026-10-26T02:00:00+00:00',
            'customer' => ['name' => 'Khách', 'phone' => '0903333444', 'email' => 'khach@example.com'],
        ])->assertCreated();

        $appointment = Appointment::query()->where('public_id', $created->json('id'))->firstOrFail();
        $token = ManageToken::issue($appointment);
        $serviceIds = $appointment->services()->pluck('service_id')->all();

        $this->patchJson('/api/v1/public/spa-a/appointments/'.$appointment->public_id.'?token='.$token, [
            'starts_at' => '2026-10-26T02:30:00+00:00',
        ])->assertOk()
            ->assertJsonPath('staff_id', $staff->public_id)
            ->assertJsonPath('starts_at', '2026-10-26T02:30:00+00:00');
        $this->assertSame($serviceIds, $appointment->fresh()->services()->pluck('service_id')->all());

        $moved = $this->postJson('/api/v1/public/spa-a/appointments', [
            'branch_id' => $branch->public_id,
            'staff_id' => $staff->public_id,
            'service_ids' => [$service->public_id],
            'starts_at' => '2026-10-26T04:00:00+00:00',
            'customer' => ['name' => 'Khách', 'phone' => '0905555666', 'email' => 'khach2@example.com'],
        ])->assertCreated();
        $second = Appointment::query()->where('public_id', $moved->json('id'))->firstOrFail();
        $secondToken = ManageToken::issue($second);
        $secondServices = $second->services()->pluck('service_id')->all();

        $this->patchJson('/api/v1/public/spa-a/appointments/'.$second->public_id.'?token='.$secondToken, [
            'starts_at' => '2026-10-26T04:30:00+00:00',
            'staff_id' => $other->public_id,
        ])->assertOk()
            ->assertJsonPath('staff_id', $other->public_id);
        $this->assertSame($secondServices, $second->fresh()->services()->pluck('service_id')->all());
    }

    /**
     * @return array{branch: Branch, staff: StaffProfile, service: Service, other: StaffProfile}
     */
    private function fixture(): array
    {
        if (config('database.default') !== 'mysql') {
            $this->fail('Overlap tests require MySQL.');
        }

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
        foreach ([1, 5] as $weekday) {
            BranchOpeningHour::query()->create([
                'branch_id' => $branch->id,
                'weekday' => $weekday,
                'opens_at' => '09:00',
                'closes_at' => '12:00',
            ]);
        }
        $service = Service::query()->create([
            'name' => 'Cắt tóc',
            'duration_minutes' => 30,
            'price_amount' => 150000,
            'is_active' => true,
        ]);
        $staff = $this->staff($tenant->id, $branch->id, $service->id, 'one@example.com');
        $other = $this->staff($tenant->id, $branch->id, $service->id, 'two@example.com');

        return compact('branch', 'staff', 'service', 'other');
    }

    private function staff(int $tenantId, int $branchId, int $serviceId, string $email): StaffProfile
    {
        $user = User::factory()->create([
            'email' => $email,
            'tenant_id' => $tenantId,
        ]);
        $user->branches()->attach($branchId, ['tenant_id' => $tenantId]);
        $profile = StaffProfile::query()->create(['user_id' => $user->id]);
        foreach ([1, 5] as $weekday) {
            StaffWorkingHour::query()->create([
                'staff_profile_id' => $profile->id,
                'weekday' => $weekday,
                'starts_at' => '09:00',
                'ends_at' => '12:00',
            ]);
        }
        $profile->services()->attach($serviceId, ['tenant_id' => $tenantId]);

        return $profile;
    }

    private function held(Branch $branch, StaffProfile $staff, ?Service $service, string $startsAt): Appointment
    {
        $start = CarbonImmutable::parse($startsAt);
        $customer = Customer::query()->create([
            'name' => 'Khách',
            'phone' => '090'.random_int(1000000, 9999999),
            'email' => 'khach@example.com',
        ]);
        $appointment = Appointment::query()->create([
            'branch_id' => $branch->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'starts_at' => $start,
            'ends_at' => $start->addMinutes(30),
            'status' => 'pending',
        ]);

        if ($service !== null) {
            AppointmentService::query()->create([
                'appointment_id' => $appointment->id,
                'service_id' => $service->id,
                'position' => 0,
                'duration_minutes' => $service->duration_minutes,
                'price_amount' => $service->price_amount,
            ]);
        }

        return $appointment;
    }

    private function login(): void
    {
        $this->withHeader('Referer', 'http://localhost:5173')->get('/sanctum/csrf-cookie');
        $this->withHeaders([
            'Referer' => 'http://localhost:5173',
            'X-XSRF-TOKEN' => csrf_token(),
        ])->postJson('/api/v1/auth/login', [
            'email' => 'owner@example.com',
            'password' => 'password',
        ])->assertOk();
    }
}
