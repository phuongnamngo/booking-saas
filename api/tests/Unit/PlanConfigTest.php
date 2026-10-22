<?php

namespace Tests\Unit;

use Tests\TestCase;

class PlanConfigTest extends TestCase
{
    public function test_plan_caps_and_frontend_url_are_configured(): void
    {
        $this->assertSame(1, config('plans.free.branches'));
        $this->assertSame(3, config('plans.free.staff'));
        $this->assertSame(20, config('plans.pro.branches'));
        $this->assertSame(100, config('plans.pro.staff'));
        $this->assertSame('http://localhost:5173', config('app.frontend_url'));
    }
}
