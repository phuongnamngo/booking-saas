<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id'])]
class StaffProfile extends Model
{
    use BelongsToTenant, HasPublicId;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'staff_services')->withTimestamps();
    }

    public function workingHours(): HasMany
    {
        return $this->hasMany(StaffWorkingHour::class);
    }

    public function scheduleExceptions(): HasMany
    {
        return $this->hasMany(StaffScheduleException::class);
    }
}
