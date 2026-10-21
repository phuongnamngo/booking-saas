<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class ServiceCategory extends Model
{
    use BelongsToTenant, HasPublicId;

    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'category_id');
    }
}
