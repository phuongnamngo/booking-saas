<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\StaffProfile;
use App\Services\AppointmentBooker;
use App\Services\AppointmentSideEffects;
use App\Services\AuditLogger;
use App\Services\AvailabilityCache;
use App\Support\ManageToken;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentManageController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $appointment = $this->appointment($request);
        $this->authorizeToken($request, $appointment);

        return response()->json($this->payload($appointment));
    }

    public function cancel(Request $request): JsonResponse
    {
        $appointment = $this->appointment($request);
        $this->authorizeToken($request, $appointment);
        $this->assertOutsideWindow($appointment);

        DB::transaction(function () use ($appointment): void {
            $logger = app(AuditLogger::class);
            $before = $logger->appointmentState($appointment);
            $this->transition($appointment, 'cancelled');
            $logger->write(null, 'appointment.cancelled', $appointment, $before, $logger->appointmentState($appointment));
            DB::afterCommit(fn () => app(AppointmentSideEffects::class)->cancelled($appointment));
        });

        return response()->json($this->payload($appointment));
    }

    public function update(Request $request, AppointmentBooker $booker): JsonResponse
    {
        $appointment = $this->appointment($request);
        $this->authorizeToken($request, $appointment);
        $this->assertOutsideWindow($appointment);

        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'staff_id' => ['sometimes', 'string'],
        ]);

        $staff = isset($data['staff_id'])
            ? StaffProfile::query()->where('public_id', $data['staff_id'])->firstOrFail()
            : null;

        $updated = $booker->reschedule(
            $appointment,
            CarbonImmutable::parse($data['starts_at']),
            $staff,
            null,
            null,
        );

        return response()->json($this->payload($updated));
    }

    private function appointment(Request $request): Appointment
    {
        $routeAppointment = $request->route('appointment');

        if ($routeAppointment instanceof Appointment) {
            return $routeAppointment;
        }

        return Appointment::query()->where('public_id', (string) $routeAppointment)->firstOrFail();
    }

    private function authorizeToken(Request $request, Appointment $appointment): void
    {
        $token = $request->query('token');

        if (! is_string($token) || ! ManageToken::matches($appointment, $token)) {
            abort(404);
        }
    }

    private function assertOutsideWindow(Appointment $appointment): void
    {
        if ($appointment->starts_at->lessThanOrEqualTo(CarbonImmutable::now('UTC')->addHours(24))) {
            throw ValidationException::withMessages([
                'starts_at' => ['Chỉ đổi hoặc hủy trước 24 giờ.'],
            ]);
        }
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
                'status' => ['Không chuyển được trạng thái này.'],
            ]);
        }

        $appointment->update(['status' => $status]);
        app(AvailabilityCache::class)->forgetAppointment($appointment->refresh());
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Appointment $appointment): array
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
