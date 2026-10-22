<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function verifyRegisteredUsers(): void
    {
        User::withoutGlobalScope('tenant')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }
}
