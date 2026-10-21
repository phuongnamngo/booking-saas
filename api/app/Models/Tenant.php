<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'timezone'])]
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory, HasPublicId;

    protected function casts(): array
    {
        return [];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
