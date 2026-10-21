<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['payment_id', 'amount', 'status', 'gateway_refund_id'])]
class Refund extends Model
{
    use BelongsToTenant;

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
