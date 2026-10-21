<?php

namespace Database\Seeders;

use App\Actions\RegisterOwner;
use App\Models\Branch;
use App\Models\Service;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoTenantSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        $owner = app(RegisterOwner::class)->handle([
            'business_name' => 'Demo Spa',
            'slug' => 'demo',
            'name' => 'Chủ demo',
            'email' => 'demo@example.com',
            'password' => 'password',
        ]);

        $tenantId = $owner->tenant_id;

        $branch = Branch::query()->create([
            'name' => 'Chi nhánh demo',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'is_active' => true,
        ]);

        $service = Service::query()->create([
            'name' => 'Chăm sóc 60 phút',
            'duration_minutes' => 60,
            'price_amount' => 300000,
            'is_active' => true,
        ]);

        $staffUser = User::query()->create([
            'name' => 'Nhân viên demo',
            'email' => 'staff@example.com',
            'password' => 'password',
            'tenant_id' => $tenantId,
        ]);
        $staffUser->assignRole('staff');

        $profile = StaffProfile::query()->create([
            'user_id' => $staffUser->id,
        ]);
        $staffUser->branches()->attach($branch->id, ['tenant_id' => $tenantId]);
        $profile->services()->attach($service->id, ['tenant_id' => $tenantId]);

        foreach (range(0, 6) as $weekday) {
            $branch->openingHours()->create([
                'weekday' => $weekday,
                'opens_at' => '09:00',
                'closes_at' => '17:00',
            ]);
            $profile->workingHours()->create([
                'weekday' => $weekday,
                'starts_at' => '09:00',
                'ends_at' => '17:00',
            ]);
        }
    }
}
