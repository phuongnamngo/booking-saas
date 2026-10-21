<?php

namespace App\Http\Controllers;

use App\Exceptions\IdempotencyReplay;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Services\AppointmentBooker;
use App\Services\AppointmentSideEffects;
use App\Services\AuditLogger;
use App\Services\AvailabilityCache;
use App\Services\IdempotencyStore;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            abort(403);
        }

        $data = $request->validate([
            'date' => ['required_without:from', 'date_format:Y-m-d'],
            'from' => ['required_without:date', 'date_format:Y-m-d'],
            'to' => ['required_with:from', 'date_format:Y-m-d'],
            'branch_id' => ['required', 'string'],
        ]);

        $branch = Branch::query()->where('public_id', $data['branch_id'])->firstOrFail();

        if ($user->hasRole('manager') && ! $branch->users()->whereKey($user->id)->exists()) {
            abort(403);
        }

        if (! $user->hasRole('owner') && ! $user->hasRole('manager') && ! $user->hasRole('staff')) {
            abort(403);
        }

        if (isset($data['date'])) {
            $start = CarbonImmutable::parse($data['date'], $branch->timezone)->startOfDay();
            $end = $start->endOfDay();
        } else {
            $start = CarbonImmutable::parse($data['from'], $branch->timezone)->startOfDay();
            $to = CarbonImmutable::parse($data['to'], $branch->timezone)->startOfDay();

            if ($to->greaterThan($start->addDays(6))) {
                throw ValidationException::withMessages([
                    'to' => 'Khoảng xem tối đa 7 ngày.',
                ]);
            }

            $end = CarbonImmutable::parse($data['to'], $branch->timezone)->endOfDay();
        }

        $appointments = Appointment::query()
            ->where('branch_id', $branch->id)
            ->where('starts_at', '<', $end->utc()->format('Y-m-d H:i:s'))
            ->where('ends_at', '>', $start->utc()->format('Y-m-d H:i:s'))
            ->when($user->hasRole('staff') && ! $user->hasRole('owner') && ! $user->hasRole('manager'), function ($query) use ($user) {
                $query->where('staff_id', $user->staffProfile?->id ?? 0);
            })
            ->orderBy('starts_at')
            ->get();

        return response()->json($appointments->map(fn (Appointment $appointment): array => [
            'id' => $appointment->public_id,
            'status' => $appointment->status,
            'starts_at' => $appointment->starts_at->toIso8601String(),
            'ends_at' => $appointment->ends_at->toIso8601String(),
        ])->values());
    }

    public function store(Request $request, AppointmentBooker $booker, IdempotencyStore $idempotency): JsonResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'string'],
            'staff_id' => ['required', 'string'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['string'],
            'starts_at' => ['required', 'date'],
            'customer.name' => ['required', 'string', 'max:255'],
            'customer.phone' => ['required', 'string', 'max:32'],
            'customer.email' => ['nullable', 'email', 'max:255'],
        ]);

        $branch = Branch::query()->where('public_id', $data['branch_id'])->firstOrFail();
        $staff = StaffProfile::query()->where('public_id', $data['staff_id'])->firstOrFail();
        $services = Service::query()
            ->whereIn('public_id', $data['service_ids'])
            ->where('is_active', true)
            ->get()
            ->keyBy('public_id');
        $ordered = collect($data['service_ids'])->map(fn (string $id) => $services->get($id));

        if ($ordered->contains(null)) {
            return response()->json([
                'message' => 'Dịch vụ không còn nhận đặt.',
            ], 422);
        }

        $idempotencyKey = $request->header('Idempotency-Key');
        $idempotencyHash = null;

        if (is_string($idempotencyKey) && $idempotencyKey !== '') {
            $idempotencyHash = $idempotency->hash($data);
            $replay = $idempotency->replay((int) $branch->tenant_id, $idempotencyKey, $idempotencyHash);

            if ($replay !== null) {
                return $replay;
            }
        }

        try {
            $appointment = $booker->create(
                $branch,
                $staff,
                $ordered,
                CarbonImmutable::parse($data['starts_at']),
                $data['customer'],
                $request->user(),
                is_string($idempotencyKey) && $idempotencyKey !== '' ? $idempotencyKey : null,
                $idempotencyHash,
            );
        } catch (IdempotencyReplay $replay) {
            return response()->json($replay->body, $replay->status);
        }

        return response()->json($this->appointmentPayload($appointment), 201);
    }

    public function update(Request $request, Appointment $appointment, AppointmentBooker $booker): JsonResponse
    {
        $this->authorize('update', $appointment);

        $data = $request->validate([
            'staff_id' => ['sometimes', 'string'],
            'service_ids' => ['sometimes', 'array', 'min:1'],
            'service_ids.*' => ['string'],
            'starts_at' => ['required', 'date'],
        ]);

        $staff = isset($data['staff_id'])
            ? StaffProfile::query()->where('public_id', $data['staff_id'])->firstOrFail()
            : null;
        $services = null;

        if (isset($data['service_ids'])) {
            $found = Service::query()->whereIn('public_id', $data['service_ids'])->where('is_active', true)->get()->keyBy('public_id');
            $services = collect($data['service_ids'])->map(fn (string $id) => $found->get($id));
            if ($services->contains(null)) {
                return response()->json(['message' => 'Dịch vụ không còn nhận đặt.'], 422);
            }
        }

        $updated = $booker->reschedule(
            $appointment->load('branch'),
            CarbonImmutable::parse($data['starts_at']),
            $staff,
            $services,
            $request->user(),
        );

        return response()->json($this->appointmentPayload($updated->load('staff')));
    }

    public function cancel(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('update', $appointment);
        DB::transaction(function () use ($request, $appointment): void {
            $logger = app(AuditLogger::class);
            $before = $logger->appointmentState($appointment);
            $this->transition($appointment, 'cancelled');
            $logger->write($request->user(), 'appointment.cancelled', $appointment, $before, $logger->appointmentState($appointment));
            DB::afterCommit(fn () => app(AppointmentSideEffects::class)->cancelled($appointment));
        });

        return response()->json($this->appointmentPayload($appointment));
    }

    public function payments(Appointment $appointment): JsonResponse
    {
        $this->authorize('view', $appointment);

        $rows = $appointment->payments()->with('refunds')->orderBy('id')->get()->map(fn (Payment $payment): array => [
            'id' => $payment->public_id,
            'amount' => $payment->amount,
            'status' => $payment->status,
            'refunds' => $payment->refunds->map(fn (Refund $refund): array => [
                'amount' => $refund->amount,
                'status' => $refund->status,
            ])->values(),
        ])->values();

        return response()->json($rows);
    }

    public function status(Request $request, Appointment $appointment): JsonResponse
    {
        $this->authorize('update', $appointment);
        $data = $request->validate([
            'status' => ['required', 'string'],
        ]);
        DB::transaction(function () use ($request, $appointment, $data): void {
            $logger = app(AuditLogger::class);
            $before = $logger->appointmentState($appointment);
            $this->transition($appointment, $data['status']);
            $logger->write($request->user(), 'appointment.status', $appointment, $before, $logger->appointmentState($appointment));
            if ($data['status'] === 'completed') {
                DB::afterCommit(fn () => app(AppointmentSideEffects::class)->completed($appointment));
            }
        });

        return response()->json($this->appointmentPayload($appointment));
    }

    private function transition(Appointment $appointment, string $status): void
    {
        $allowed = [
            'pending' => ['confirmed', 'cancelled'],
            'confirmed' => ['in_progress', 'cancelled', 'no_show'],
            'in_progress' => ['completed', 'no_show'],
        ];

        if (! in_array($status, $allowed[$appointment->status] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => 'Không chuyển được trạng thái này.',
            ]);
        }

        $appointment->update(['status' => $status]);
        app(AvailabilityCache::class)->forgetAppointment($appointment->refresh());
    }

    /**
     * @return array<string, mixed>
     */
    private function appointmentPayload(Appointment $appointment): array
    {
        $appointment->loadMissing('staff');

        return [
            'id' => $appointment->public_id,
            'staff_id' => $appointment->staff?->public_id,
            'status' => $appointment->status,
            'starts_at' => $appointment->starts_at->toIso8601String(),
            'ends_at' => $appointment->ends_at->toIso8601String(),
        ];
    }
}
