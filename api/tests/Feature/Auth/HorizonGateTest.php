<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class HorizonGateTest extends TestCase
{
    public function test_only_super_admins_can_view_horizon(): void
    {
        $owner = new User(['is_super_admin' => false]);
        $admin = new User(['is_super_admin' => true]);

        $this->assertFalse(Gate::forUser($owner)->allows('viewHorizon'));
        $this->assertTrue(Gate::forUser($admin)->allows('viewHorizon'));
        $this->assertFalse(Gate::allows('viewHorizon'));
    }
}
