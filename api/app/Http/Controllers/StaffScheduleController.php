<?php

namespace App\Http\Controllers;

use App\Models\StaffProfile;
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

        DB::transaction(function () use ($staff, $data): void {
            $staff->workingHours()->delete();

            foreach ($data['intervals'] as $interval) {
                $staff->workingHours()->create($interval);
            }
        });

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

        $exception = $staff->scheduleExceptions()->create($data);

        return response()->json([
            'type' => $exception->type,
            'starts_at' => $exception->starts_at->toIso8601String(),
            'ends_at' => $exception->ends_at->toIso8601String(),
        ], 201);
    }
}
