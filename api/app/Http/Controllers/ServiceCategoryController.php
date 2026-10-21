<?php

namespace App\Http\Controllers;

use App\Http\Resources\ServiceCategoryResource;
use App\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ServiceCategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', ServiceCategory::class);

        return ServiceCategoryResource::collection(ServiceCategory::query()->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', ServiceCategory::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $category = ServiceCategory::query()->create($data);

        return (new ServiceCategoryResource($category))->response()->setStatusCode(201);
    }

    public function show(ServiceCategory $serviceCategory): ServiceCategoryResource
    {
        $this->authorize('view', $serviceCategory);

        return new ServiceCategoryResource($serviceCategory);
    }

    public function update(Request $request, ServiceCategory $serviceCategory): ServiceCategoryResource
    {
        $this->authorize('update', $serviceCategory);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);
        $serviceCategory->update($data);

        return new ServiceCategoryResource($serviceCategory);
    }

    public function destroy(ServiceCategory $serviceCategory): JsonResponse
    {
        $this->authorize('delete', $serviceCategory);
        $serviceCategory->delete();

        return response()->json(null, 204);
    }
}
