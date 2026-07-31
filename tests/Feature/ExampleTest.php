<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Guests hitting the root URL get the public landing page (AR-60),
     * not a redirect. Authenticated users are redirected to their
     * dashboard by the same route; that path is covered separately by
     * the auth/dashboard feature tests.
     */
    public function test_root_returns_landing_page_for_guests(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Real-time catering delivery tracking', false);
        $response->assertSee('Track a delivery', false);
        $response->assertSee('Staff log in', false);
    }
}
