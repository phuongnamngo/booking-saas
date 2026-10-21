<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public const NAMES = [
        'branches.manage',
        'services.manage',
        'staff.manage',
        'schedules.manage',
        'appointments.manage',
        'appointments.handle',
        'invitations.manage',
    ];

    public function run(): void
    {
        foreach (self::NAMES as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }
}
