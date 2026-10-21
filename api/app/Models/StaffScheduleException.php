<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['staff_profile_id', 'type', 'starts_at', 'ends_at'])]
class StaffScheduleException extends Model
{
    use BelongsToTenant;

    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

    protected function casts(): array
    {
        return [
            'starts_at' => UtcDateTime::class,
            'ends_at' => UtcDateTime::class,
        ];
    }
}
