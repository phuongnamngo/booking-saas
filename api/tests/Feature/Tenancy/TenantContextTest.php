<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class TenantContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_query_without_tenant_context_throws(): void
    {
        $this->expectException(LogicException::class);

        User::query()->get();
    }
}
