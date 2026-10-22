<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\Tenant;
use Illuminate\Support\Str;

class ManageToken
{
    public static function issue(Appointment $appointment): string
    {
        $token = Str::random(40);
        $appointment->forceFill([
            'manage_token_hash' => hash('sha256', $token),
        ])->save();

        return $token;
    }

    public static function matches(Appointment $appointment, string $token): bool
    {
        $hash = $appointment->manage_token_hash;

        if (! is_string($hash) || $hash === '') {
            return false;
        }

        return hash_equals($hash, hash('sha256', $token));
    }

    public static function url(Appointment $appointment, string $token): string
    {
        $slug = Tenant::query()->whereKey($appointment->tenant_id)->value('slug');

        return config('app.frontend_url').'/t/'.$slug.'/appointments/'.$appointment->public_id.'?token='.$token;
    }
}
