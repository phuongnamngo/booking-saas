<?php

namespace App\Http\Controllers;

use App\Http\Resources\ServiceResource;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Services\AuditLogger;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Service::class);

        return ServiceResource::collection(
            Service::query()->with('category')->orderBy('name')->get(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Service::class);
        $service = DB::transaction(function () use ($request) {
            $data = $this->validated($request);
            $service = Service::query()->create($data);
            app(AuditLogger::class)->write($request->user(), 'service.saved', $service, null, $this->serviceAudit($data));

            return $service;
        });

        return (new ServiceResource($service->load('category')))->response()->setStatusCode(201);
    }

    public function show(Service $service): ServiceResource
    {
        $this->authorize('view', $service);

        return new ServiceResource($service->load('category'));
    }

    public function update(Request $request, Service $service): ServiceResource
    {
        $this->authorize('update', $service);
        DB::transaction(function () use ($request, $service): void {
            $before = $this->serviceAudit($service->only(['name', 'duration_minutes', 'price_amount', 'is_active']));
            $data = $this->validated($request);
            $service->update($data);
            app(AuditLogger::class)->write($request->user(), 'service.saved', $service, $before, $this->serviceAudit($data));
        });

        return new ServiceResource($service->load('category'));
    }

    public function destroy(Service $service): JsonResponse
    {
        $this->authorize('delete', $service);
        $service->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'category_id' => [
                'nullable',
                'string',
                Rule::exists('service_categories', 'public_id')->where(
                    fn ($query) => $query->where('tenant_id', app(TenantContext::class)->id()),
                ),
            ],
            'name' => ['required', 'string', 'max:255'],
            'duration_minutes' => ['required', 'integer', 'min:1'],
            'price_amount' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('category_id', $data)) {
            $data['category_id'] = $data['category_id'] === null
                ? null
                : ServiceCategory::query()->where('public_id', $data['category_id'])->value('id');
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function serviceAudit(array $data): array
    {
        return array_intersect_key($data, array_flip(['name', 'duration_minutes', 'price_amount', 'is_active']));
    }
}
