<?php

namespace App\Actions;

use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RegisterOwner
{
    /**
     * @param  array{business_name: string, slug: string, name: string, email: string, password: string}  $data
     */
    public function handle(array $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            $tenant = Tenant::query()->create([
                'name' => $data['business_name'],
                'slug' => $data['slug'],
                'timezone' => 'Asia/Ho_Chi_Minh',
            ]);

            app(TenantContext::class)->set($tenant);
            setPermissionsTeamId($tenant->id);

            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'tenant_id' => $tenant->id,
            ]);

            $manage = [
                'branches.manage',
                'services.manage',
                'staff.manage',
                'schedules.manage',
                'appointments.manage',
                'invitations.manage',
            ];

            foreach (['owner', 'manager'] as $roleName) {
                Role::findOrCreate($roleName, 'web')->syncPermissions($manage);
            }

            Role::findOrCreate('staff', 'web')->syncPermissions(['appointments.handle']);
            $user->assignRole('owner');

            return $user;
        });

        $user->sendEmailVerificationNotification();

        return $user;
    }
}
