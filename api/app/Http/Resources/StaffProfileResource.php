<?php

namespace App\Http\Resources;

use App\Models\StaffProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin StaffProfile */
class StaffProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->user?->name,
            'user_id' => $this->user?->public_id,
            'branch_ids' => $this->user?->branches->pluck('public_id')->values() ?? [],
            'service_ids' => $this->services->pluck('public_id')->values(),
        ];
    }
}
