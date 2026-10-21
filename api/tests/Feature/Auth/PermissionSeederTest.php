<?php

namespace Tests\Feature\Auth;

use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_permissions_exist(): void
    {
        $this->seed(PermissionSeeder::class);

        $this->assertSame(7, Permission::query()->count());
    }
}
