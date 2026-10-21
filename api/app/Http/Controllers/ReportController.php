<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\User;
use App\Services\ReportBuilder;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class ReportController extends Controller
{
    public function index(Request $request, ReportBuilder $reports): JsonResponse
    {
        $user = $request->user();
        if ($user === null || ! $user->can('appointments.manage')) {
            abort(403);
        }

        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d'],
            'branch_id' => ['nullable', 'string'],
            'group' => ['nullable', 'in:day,week,month'],
        ]);
        $group = $data['group'] ?? 'day';
        $branchPublicId = $data['branch_id'] ?? '';
        $branches = $this->branches($user, $branchPublicId === '' ? null : $branchPublicId);
        $key = sprintf(
            'reports:%d:%d:%s:%s:%s:%s',
            app(TenantContext::class)->id(),
            $user->id,
            $data['from'],
            $data['to'],
            $branchPublicId,
            $group,
        );

        $report = Cache::remember(
            $key,
            60,
            fn (): array => $reports->build($branches, $data['from'], $data['to'], $group),
        );

        return response()->json($report);
    }

    /**
     * @return Collection<int, Branch>
     */
    private function branches(User $user, ?string $branchPublicId): Collection
    {
        if ($branchPublicId !== null) {
            $branch = Branch::query()->where('public_id', $branchPublicId)->firstOrFail();
            if ($user->hasRole('manager') && ! $user->hasRole('owner') && ! $branch->users()->whereKey($user->id)->exists()) {
                abort(403);
            }

            return collect([$branch]);
        }

        if ($user->hasRole('owner')) {
            return Branch::query()->get();
        }

        if ($user->hasRole('manager')) {
            return $user->branches()->get();
        }

        abort(403);
    }
}
