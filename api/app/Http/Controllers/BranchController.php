<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use App\Services\AuditLogger;
use App\Services\PlanLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class BranchController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Branch::class);

        $query = Branch::query()->orderBy('name');

        if (! $request->user()->hasRole('owner')) {
            $query->whereHas('users', fn ($users) => $users->whereKey($request->user()->id));
        }

        return BranchResource::collection($query->get());
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        $branch = DB::transaction(function () use ($request): Branch {
            app(PlanLimiter::class)->assertCanCreate('branches');
            $branch = Branch::query()->create($request->validated());
            app(AuditLogger::class)->write($request->user(), 'branch.saved', $branch, null, $request->validated());

            return $branch;
        });

        return (new BranchResource($branch))->response()->setStatusCode(201);
    }

    public function show(Branch $branch): BranchResource
    {
        $this->authorize('view', $branch);

        return new BranchResource($branch);
    }

    public function update(StoreBranchRequest $request, Branch $branch): BranchResource
    {
        $this->authorize('update', $branch);
        DB::transaction(function () use ($request, $branch): void {
            $before = $branch->only(['name', 'address', 'phone', 'timezone', 'is_active']);
            $branch->update($request->validated());
            app(AuditLogger::class)->write($request->user(), 'branch.saved', $branch, $before, $request->validated());
        });

        return new BranchResource($branch);
    }

    public function destroy(Branch $branch): JsonResponse
    {
        $this->authorize('delete', $branch);
        $branch->delete();

        return response()->json(null, 204);
    }
}
