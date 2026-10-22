<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payment_id', 'appointment_id', 'amount', 'currency', 'customer_name', 'lines', 'refunded_amount', 'refunded_at', 'issued_at'])]
class Invoice extends Model
{
    use BelongsToTenant, HasPublicId;

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    protected function casts(): array
    {
        return [
            'lines' => 'array',
            'issued_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }
}
