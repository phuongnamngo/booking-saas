<?php

namespace App\Http\Resources;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Service */
class ServiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'category_id' => $this->category?->public_id,
            'name' => $this->name,
            'duration_minutes' => $this->duration_minutes,
            'price_amount' => $this->price_amount,
            'is_active' => $this->is_active,
        ];
    }
}
