<?php

namespace Tests\Feature\Booking;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\BranchOpeningHour;
use App\Models\IdempotencyKey;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\StaffWorkingHour;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_key_replays_and_a_different_body_is_rejected(): void
    {
        ['branch' => $branch, 'staff' => $staff, 'service' => $service] = $this->fixture();
        $body = $this->payload($branch, $staff, $service, '0901111222', '2026-10-26T02:00:00+00:00');

        $first = $this->withHeader('Idempotency-Key', 'same-key')
            ->postJson('/api/v1/public/spa-a/appointments', $body)
            ->assertCreated();
        $second = $this->withHeader('Idempotency-Key', 'same-key')
            ->postJson('/api/v1/public/spa-a/appointments', $body)
            ->assertCreated();

        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertArrayNotHasKey('url', $first->json());
        $this->assertSame(1, Appointment::query()->count());

        $body['customer']['name'] = 'Khách khác';
        $this->withHeader('Idempotency-Key', 'same-key')
            ->postJson('/api/v1/public/spa-a/appointments', $body)
            ->assertStatus(422);
        $this->assertSame(1, Appointment::query()->count());
    }

    public function test_requests_without_a_key_create_two_appointments(): void
    {
        ['branch' => $branch, 'staff' => $staff, 'service' => $service] = $this->fixture();

        $this->postJson('/api/v1/public/spa-a/appointments', $this->payload($branch, $staff, $service, '0901111222', '2026-10-26T02:00:00+00:00'))
            ->assertCreated();
        $this->postJson('/api/v1/public/spa-a/appointments', $this->payload($branch, $staff, $service, '0903333444', '2026-10-26T02:30:00+00:00'))
            ->assertCreated();

        $this->assertSame(2, Appointment::query()->count());
    }

    public function test_expired_key_can_be_used_again(): void
    {
        ['branch' => $branch, 'staff' => $staff, 'service' => $service] = $this->fixture();
        $body = $this->payload($branch, $staff, $service, '0901111222', '2026-10-26T02:00:00+00:00');

        $created = $this->withHeader('Idempotency-Key', 'same-key')
            ->postJson('/api/v1/public/spa-a/appointments', $body)
            ->assertCreated();

        Appointment::query()->where('public_id', $created->json('id'))->update(['status' => 'cancelled']);
        IdempotencyKey::query()->update(['expires_at' => now()->subMinute()]);

        $this->withHeader('Idempotency-Key', 'same-key')
            ->postJson('/api/v1/public/spa-a/appointments', $body)
            ->assertCreated();

        $this->assertSame(2, Appointment::query()->count());
    }

    /**
     * @return array{branch: Branch, staff: StaffProfile, service: Service}
     */
    private function fixture(): array
    {
        if (config('database.default') !== 'mysql') {
            $this->fail('Overlap tests require MySQL.');
        }

        $tenant = Tenant::factory()->create(['slug' => 'spa-a']);
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
    private function payload(Branch $branch, StaffProfile $staff, Service $service, string $phone, string $startsAt): array
    {
        return [
            'branch_id' => $branch->public_id,
            'staff_id' => $staff->public_id,
            'service_ids' => [$service->public_id],
            'starts_at' => $startsAt,
            'customer' => [
                'name' => 'Khách A',
                'phone' => $phone,
                'email' => 'khach@example.com',
            ],
        ];
    }
}
