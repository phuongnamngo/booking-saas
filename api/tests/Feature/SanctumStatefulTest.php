<?php

namespace Tests\Feature;

use Tests\TestCase;

class SanctumStatefulTest extends TestCase
{
    public function test_spa_can_fetch_csrf_cookie_and_health_stays_ok(): void
    {
        $this->withHeader('Referer', 'http://localhost:5173')
            ->get('/sanctum/csrf-cookie')
            ->assertNoContent();

        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }
}
