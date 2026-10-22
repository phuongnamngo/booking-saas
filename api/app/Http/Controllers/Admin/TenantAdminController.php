<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantAdminController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = Tenant::query()->orderBy('name')->get()->map(fn (Tenant $tenant): array => [
            'id' => $tenant->public_id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'plan' => $tenant->plan,
            'suspended_at' => $tenant->suspended_at?->toIso8601String(),
        ])->values();

        return response()->json($rows);
    }

    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'plan' => ['required', 'in:free,pro'],
        ]);
        $tenant->update(['plan' => $data['plan']]);

        return response()->json([
            'id' => $tenant->public_id,
            'plan' => $tenant->plan,
        ]);
    }

    public function suspend(Tenant $tenant): JsonResponse
    {
        $tenant->update(['suspended_at' => now()]);

        return response()->json([
            'id' => $tenant->public_id,
            'suspended_at' => $tenant->suspended_at?->toIso8601String(),
        ]);
    }

    public function unsuspend(Tenant $tenant): JsonResponse
    {
        $tenant->update(['suspended_at' => null]);

        return response()->json([
            'id' => $tenant->public_id,
            'suspended_at' => null,
        ]);
    }
}
