<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\BranchResource;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\StaffProfileResource;
use App\Models\Branch;
use App\Models\Service;
use App\Models\StaffProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CatalogController extends Controller
{
    public function branches(): AnonymousResourceCollection
    {
        return BranchResource::collection(
            Branch::query()->where('is_active', true)->orderBy('name')->get(),
        );
    }

    public function staff(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'branch_id' => ['required', 'string'],
            'service_ids' => ['required', 'array', 'min:1'],
            'service_ids.*' => ['string'],
        ]);

        $branch = Branch::query()->where('public_id', $data['branch_id'])->where('is_active', true)->first();
        $serviceIds = array_values(array_unique($data['service_ids']));
        $services = Service::query()->whereIn('public_id', $serviceIds)->where('is_active', true)->get();

        if ($branch === null || $services->count() !== count($serviceIds)) {
            return StaffProfileResource::collection(collect());
        }

        $query = StaffProfile::query()->with(['user.branches', 'services'])
            ->whereHas('user.branches', fn ($branches) => $branches->whereKey($branch->id));

        foreach ($services as $service) {
            $query->whereHas('services', fn ($related) => $related->whereKey($service->id));
        }

        return StaffProfileResource::collection($query->orderBy('id')->get());
    }

    public function services(): AnonymousResourceCollection
    {
        return ServiceResource::collection(
            Service::query()->with('category')->where('is_active', true)->orderBy('name')->get(),
        );
    }
}
