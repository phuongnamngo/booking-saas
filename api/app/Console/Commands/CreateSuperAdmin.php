<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Console\Command;

class CreateSuperAdmin extends Command
{
    protected $signature = 'admin:create';

    protected $description = 'Tạo Super Admin từ SUPER_ADMIN_EMAIL và SUPER_ADMIN_PASSWORD';

    public function handle(): int
    {
        $email = env('SUPER_ADMIN_EMAIL');
        $password = env('SUPER_ADMIN_PASSWORD');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            $this->error('SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD are required.');

            return self::FAILURE;
        }

        app(TenantContext::class)->forget();
        $user = User::withoutGlobalScope('tenant')->create([
            'name' => 'Super Admin',
            'email' => $email,
            'password' => $password,
            'tenant_id' => null,
            'is_super_admin' => true,
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return self::SUCCESS;
    }
}
