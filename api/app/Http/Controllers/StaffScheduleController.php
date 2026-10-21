<?php

namespace App\Http\Controllers;

use App\Models\StaffProfile;
use App\Services\AuditLogger;
use App\Services\AvailabilityCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffScheduleController extends Controller
{
    public function replaceWorkingHours(Request $request, StaffProfile $staff): JsonResponse
    {
        $this->authorize('update', $staff);

        $data = $request->validate([
            'intervals' => ['present', 'array'],
            'intervals.*.weekday' => ['required', 'integer', 'between:0,6'],
            'intervals.*.starts_at' => ['required', 'date_format:H:i'],
            'intervals.*.ends_at' => ['required', 'date_format:H:i', 'after:intervals.*.starts_at'],
        ]);

        DB::transaction(function () use ($request, $staff, $data): void {
            $staff->workingHours()->delete();

            foreach ($data['intervals'] as $interval) {
                $staff->workingHours()->create($interval);
            }

            app(AuditLogger::class)->write(
                $request->user(),
                'working_hours.replaced',
                $staff,
                null,
                ['intervals' => $data['intervals']],
            );
        });

        app(AvailabilityCache::class)->forgetStaff((int) $staff->tenant_id, $staff->public_id);

        return response()->json(null, 200);
    }

    public function storeException(Request $request, StaffProfile $staff): JsonResponse
    {
        $this->authorize('update', $staff);

        $data = $request->validate([
            'type' => ['required', 'in:off,extra'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ]);

        $exception = DB::transaction(function () use ($request, $staff, $data) {
            $exception = $staff->scheduleExceptions()->create($data);
            app(AuditLogger::class)->write($request->user(), 'exception.created', $staff, null, [
                'type' => $exception->type,
                'starts_at' => $exception->starts_at->toIso8601String(),
                'ends_at' => $exception->ends_at->toIso8601String(),
            ]);

            return $exception;
        });
        app(AvailabilityCache::class)->forgetStaff((int) $staff->tenant_id, $staff->public_id);

        return response()->json([
            'type' => $exception->type,
            'starts_at' => $exception->starts_at->toIso8601String(),
            'ends_at' => $exception->ends_at->toIso8601String(),
        ], 201);
    }
}
