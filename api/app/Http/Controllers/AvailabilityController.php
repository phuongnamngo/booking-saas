<?php

namespace App\Http\Controllers;

use App\Http\Requests\AvailabilityRequest;
use App\Models\Branch;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Services\AvailabilityService;
use Illuminate\Http\JsonResponse;

class AvailabilityController extends Controller
{
    public function __invoke(AvailabilityRequest $request, AvailabilityService $availability): JsonResponse
    {
        $serviceIds = array_values(array_unique($request->validated('service_ids')));
        $branch = Branch::query()->where('public_id', $request->validated('branch_id'))->first();
        $staff = StaffProfile::query()->where('public_id', $request->validated('staff_id'))->first();
        $services = Service::query()->whereIn('public_id', $serviceIds)->where('is_active', true)->get();

        if ($branch === null || $staff === null || $services->count() !== count($serviceIds)) {
            return response()->json(['slots' => []]);
        }

        $slots = $availability->slots($branch, $staff, $services, $request->validated('date'));

        return response()->json([
            'slots' => array_map(fn (array $slot): array => [
                'starts_at' => $slot['starts_at']->toIso8601String(),
                'ends_at' => $slot['ends_at']->toIso8601String(),
            ], $slots),
        ]);
    }
}
