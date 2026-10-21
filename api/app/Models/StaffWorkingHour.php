<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['staff_profile_id', 'weekday', 'starts_at', 'ends_at'])]
class StaffWorkingHour extends Model
{
    use BelongsToTenant;

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
        ];
    }
}
