<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
        $branch = Branch::query()->create($request->validated());

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
        $branch->update($request->validated());

        return new BranchResource($branch);
    }

    public function destroy(Branch $branch): JsonResponse
    {
        $this->authorize('delete', $branch);
        $branch->delete();

        return response()->json(null, 204);
    }
}
