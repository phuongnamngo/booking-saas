<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegisterOwnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_creates_tenant_owner_and_three_roles(): void
    {
        $this->seed(PermissionSeeder::class);

        $this->postJson('/api/v1/auth/register', [
            'business_name' => 'Spa Một',
            'slug' => 'spa-mot',
            'name' => 'An',
            'email' => 'an@example.com',
            'password' => 'password',
        ])->assertCreated();

        $this->assertDatabaseCount('tenants', 1);
        $this->assertDatabaseCount('users', 1);

        $user = User::withoutGlobalScope('tenant')->first();
        setPermissionsTeamId($user->tenant_id);

        $this->assertTrue($user->hasRole('owner'));
        $this->assertSame(3, Role::query()->where('tenant_id', $user->tenant_id)->count());
    }
}
