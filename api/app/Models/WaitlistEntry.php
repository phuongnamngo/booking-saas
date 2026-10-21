<?php

namespace App\Models;

use App\Casts\UtcDateTime;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['branch_id', 'staff_id', 'service_public_ids', 'starts_at', 'customer_name', 'customer_phone', 'customer_email', 'notified_at'])]
class WaitlistEntry extends Model
{
    use BelongsToTenant;

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'staff_id');
    }

    protected function casts(): array
    {
        return [
            'service_public_ids' => 'array',
            'starts_at' => UtcDateTime::class,
            'notified_at' => 'datetime',
        ];
    }
}
