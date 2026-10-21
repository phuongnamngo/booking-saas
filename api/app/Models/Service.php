<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['category_id', 'name', 'duration_minutes', 'price_amount', 'is_active'])]
class Service extends Model
{
    use BelongsToTenant, HasPublicId;

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'price_amount' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
