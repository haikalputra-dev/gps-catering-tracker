<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Http\Requests\Tracking\TrackingAuthenticateRequest;
use App\Models\Delivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * POST /track throttling.
 *
 * The route uses the framework's `throttle:10,15` alias (10 attempts
 * per 15 minutes per client). This test drives the rate limiter to
 * exhaustion and asserts a 429 response, then confirms that the
 * per-user counter resets when the limiter is cleared (AR-42 revised).
 */
class TrackingThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure a clean rate-limiter slate between test methods; the
        // throttle middleware keys on IP + route by default, so a
        // stale counter from another test could bleed in.
        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Compute the throttle key used by
     * `\Illuminate\Routing\Middleware\ThrottleRequests::resolveRequestSignature`
     * for an unauthenticated request against the tracking route.
     */
    private function throttleKey(): string
    {
        // ThrottleRequests uses `sha1($route->getDomain().'|'.$request->ip())`
        // as the request signature for unauthenticated requests. The
        // test client leaves the domain null and the IP defaults to
        // 127.0.0.1, so the effective input is '|127.0.0.1'.
        return sha1('|127.0.0.1');
    }

    public function test_eleventh_attempt_within_window_is_rate_limited(): void
    {
        $delivery = Delivery::factory()->scheduled()->create([
            'customer_phone' => '+628123454321',
        ]);

        $badPayload = [
            'receipt_number' => $delivery->receipt_number,
            'phone_last_four' => '9999',
        ];

        // First 10 attempts return a 302 redirect back to the form with
        // the generic error.
        for ($i = 1; $i <= 10; $i++) {
            $response = $this->post(route('tracking.authenticate'), $badPayload);
            $response->assertRedirect(route('tracking.form'));
            $response->assertSessionHasErrors(['form' => TrackingAuthenticateRequest::GENERIC_ERROR]);
        }

        // The 11th attempt in the same window must be throttled.
        $blocked = $this->post(route('tracking.authenticate'), $badPayload);
        $blocked->assertStatus(429);
    }

    public function test_successful_attempts_also_consume_the_limit(): void
    {
        // The throttle middleware is oblivious to success vs. failure
        // (it counts requests, not failed authentications), so ten
        // successful lookups also lock the route.
        $delivery = Delivery::factory()->scheduled()->create([
            'customer_phone' => '+628123454321',
        ]);
        $goodPayload = [
            'receipt_number' => $delivery->receipt_number,
            'phone_last_four' => '4321',
        ];

        for ($i = 1; $i <= 10; $i++) {
            $response = $this->post(route('tracking.authenticate'), $goodPayload);
            $response->assertRedirect(route('tracking.status'));
        }

        $blocked = $this->post(route('tracking.authenticate'), $goodPayload);
        $blocked->assertStatus(429);
    }

    public function test_clearing_the_limiter_restores_access(): void
    {
        $badPayload = [
            'receipt_number' => 'DEL-20260101-ZZZZ',
            'phone_last_four' => '9999',
        ];

        for ($i = 1; $i <= 10; $i++) {
            $this->post(route('tracking.authenticate'), $badPayload);
        }

        $this->post(route('tracking.authenticate'), $badPayload)
            ->assertStatus(429);

        RateLimiter::clear($this->throttleKey());

        $this->post(route('tracking.authenticate'), $badPayload)
            ->assertRedirect(route('tracking.form'));
    }

    public function test_get_requests_are_not_throttled(): void
    {
        // Only POST /track carries the throttle; the GET form must
        // stay reachable regardless of prior POST volume.
        $badPayload = [
            'receipt_number' => 'DEL-20260101-ZZZZ',
            'phone_last_four' => '9999',
        ];

        for ($i = 1; $i <= 10; $i++) {
            $this->post(route('tracking.authenticate'), $badPayload);
        }

        $this->get(route('tracking.form'))->assertOk();
    }
}
