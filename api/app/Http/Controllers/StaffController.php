<?php

namespace App\Http\Controllers;

use App\Http\Resources\StaffProfileResource;
use App\Models\Branch;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', StaffProfile::class);

        return StaffProfileResource::collection(
            StaffProfile::query()->with(['user.branches', 'services'])->orderBy('id')->get(),
        );
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', StaffProfile::class);
        $profile = $this->save($request, new StaffProfile);

        return (new StaffProfileResource($profile))->response()->setStatusCode(201);
    }

    public function show(StaffProfile $staff): StaffProfileResource
    {
        $this->authorize('view', $staff);

        return new StaffProfileResource($staff->load(['user.branches', 'services']));
    }

    public function update(Request $request, StaffProfile $staff): StaffProfileResource
    {
        $this->authorize('update', $staff);

        return new StaffProfileResource($this->save($request, $staff));
    }

    public function destroy(StaffProfile $staff): JsonResponse
    {
        $this->authorize('delete', $staff);
        $staff->user?->branches()->detach();
        $staff->delete();

        return response()->json(null, 204);
    }

    private function save(Request $request, StaffProfile $profile): StaffProfile
    {
        $tenantId = app(TenantContext::class)->id();
        $data = $request->validate([
            'user_id' => [
                'required',
                'string',
                Rule::exists('users', 'public_id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'branch_ids' => ['present', 'array'],
            'branch_ids.*' => [
                'string',
                Rule::exists('branches', 'public_id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
            'service_ids' => ['present', 'array'],
            'service_ids.*' => [
                'string',
                Rule::exists('services', 'public_id')->where(fn ($query) => $query->where('tenant_id', $tenantId)),
            ],
        ]);

        return DB::transaction(function () use ($request, $data, $profile, $tenantId): StaffProfile {
            $user = User::query()->where('public_id', $data['user_id'])->firstOrFail();

            $duplicate = StaffProfile::query()
                ->where('user_id', $user->id)
                ->when($profile->exists, fn ($query) => $query->whereKeyNot($profile->id))
                ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'user_id' => 'Nhân viên đã có hồ sơ.',
                ]);
            }

            $before = $profile->exists ? ['user_id' => $profile->user?->public_id] : null;
            $profile->user_id = $user->id;
            $profile->save();

            $branchIds = Branch::query()->whereIn('public_id', $data['branch_ids'])->pluck('id');
            $user->branches()->sync(
                $branchIds->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $tenantId]])->all(),
            );

            $serviceIds = Service::query()->whereIn('public_id', $data['service_ids'])->pluck('id');
            $profile->services()->sync(
                $serviceIds->mapWithKeys(fn ($id) => [$id => ['tenant_id' => $tenantId]])->all(),
            );

            app(AuditLogger::class)->write($request->user(), 'staff.saved', $profile, $before, [
                'user_id' => $user->public_id,
                'branch_ids' => $data['branch_ids'],
                'service_ids' => $data['service_ids'],
            ]);

            return $profile->load(['user.branches', 'services']);
        });
    }
}
