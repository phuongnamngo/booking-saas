<?php

namespace App\Actions;

use App\Models\Invitation;
use App\Models\StaffProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptInvitation
{
    /**
     * @param  array{token: string, name: string, password: string}  $data
     */
    public function handle(array $data): User
    {
        $hashed = hash('sha256', $data['token']);
        $invitation = Invitation::withoutGlobalScope('tenant')
            ->where('token_hash', $hashed)
            ->first();

        if (
            $invitation === null
            || ! hash_equals($invitation->token_hash, $hashed)
            || $invitation->accepted_at !== null
            || $invitation->expires_at->isPast()
        ) {
            throw ValidationException::withMessages([
                'token' => 'Lời mời không còn hiệu lực.',
            ]);
        }

        if (User::withoutGlobalScope('tenant')->where('email', $invitation->email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'Email đã có tài khoản.',
            ]);
        }

        return DB::transaction(function () use ($data, $invitation): User {
            $tenant = Tenant::query()->findOrFail($invitation->tenant_id);
            app(TenantContext::class)->set($tenant);
            setPermissionsTeamId($tenant->id);

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $invitation->email,
                'password' => $data['password'],
                'tenant_id' => $tenant->id,
            ]);

            $user->assignRole($invitation->role);

            if ($invitation->role === 'staff') {
                StaffProfile::query()->create(['user_id' => $user->id]);
            }

            $invitation->forceFill(['accepted_at' => now()])->save();

            return $user;
        });
    }
}
