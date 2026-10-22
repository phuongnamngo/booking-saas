<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $tenant = null;
        $role = null;
        $permissions = [];

        if ($this->tenant_id !== null) {
            $tenant = [
                'id' => $this->tenant?->public_id,
                'name' => $this->tenant?->name,
                'slug' => $this->tenant?->slug,
            ];
            $role = $this->getRoleNames()->first();
            $permissions = $this->getAllPermissions()->pluck('name')->values();
        }

        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'is_super_admin' => (bool) $this->is_super_admin,
            'tenant' => $tenant,
            'role' => $role,
            'permissions' => $permissions,
        ];
    }
}
