<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchOpeningHour;
use App\Services\AuditLogger;
use App\Services\AvailabilityCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BranchScheduleController extends Controller
{
    public function replaceOpeningHours(Request $request, Branch $branch): JsonResponse
    {
        $this->authorize('update', $branch);

        $data = $request->validate([
            'intervals' => ['present', 'array'],
            'intervals.*.weekday' => ['required', 'integer', 'between:0,6'],
            'intervals.*.opens_at' => ['required', 'date_format:H:i'],
            'intervals.*.closes_at' => ['required', 'date_format:H:i', 'after:intervals.*.opens_at'],
        ]);

        DB::transaction(function () use ($request, $branch, $data): void {
            $before = $branch->openingHours()
                ->orderBy('weekday')
                ->orderBy('opens_at')
                ->get()
                ->map(fn (BranchOpeningHour $hour): array => [
                    'weekday' => $hour->weekday,
                    'opens_at' => substr((string) $hour->opens_at, 0, 5),
                    'closes_at' => substr((string) $hour->closes_at, 0, 5),
                ])
                ->values()
                ->all();
            $branch->openingHours()->delete();

            foreach ($data['intervals'] as $interval) {
                $branch->openingHours()->create([
                    'weekday' => $interval['weekday'],
                    'opens_at' => $interval['opens_at'],
                    'closes_at' => $interval['closes_at'],
                ]);
            }

            app(AuditLogger::class)->write(
                $request->user(),
                'opening_hours.replaced',
                $branch,
                ['intervals' => $before],
                ['intervals' => $data['intervals']],
            );
        });

        app(AvailabilityCache::class)->forgetBranch((int) $branch->tenant_id, $branch->public_id);

        return response()->json([
            'intervals' => $branch->openingHours()
                ->orderBy('weekday')
                ->orderBy('opens_at')
                ->get()
                ->map(fn (BranchOpeningHour $hour): array => [
                    'weekday' => $hour->weekday,
                    'opens_at' => substr((string) $hour->opens_at, 0, 5),
                    'closes_at' => substr((string) $hour->closes_at, 0, 5),
                ])
                ->values(),
        ]);
    }

    public function storeHoliday(Request $request, Branch $branch): JsonResponse
    {
        $this->authorize('update', $branch);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $holiday = DB::transaction(function () use ($request, $branch, $data) {
            $holiday = $branch->holidays()->create($data);
            app(AuditLogger::class)->write($request->user(), 'holiday.created', $branch, null, [
                'date' => $holiday->date->toDateString(),
                'name' => $holiday->name,
            ]);

            return $holiday;
        });
        app(AvailabilityCache::class)->forgetBranch((int) $branch->tenant_id, $branch->public_id);

        return response()->json([
            'date' => $holiday->date->toDateString(),
            'name' => $holiday->name,
        ], 201);
    }
}
