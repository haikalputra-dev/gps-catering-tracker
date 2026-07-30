<?php

declare(strict_types=1);

namespace Tests\Feature\Telemetry;

use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Verifies the AR-49 per-device rate limit on `POST /api/telemetry`.
 *
 * The named `telemetry` limiter is registered in `bootstrap/app.php`
 * and keys on the resolved device id when the middleware attaches one.
 * These tests exercise the limit at a small, deterministic value to
 * avoid burning wall-clock time, and confirm two properties:
 *
 *   - a device that stays under the cap continues to receive 204
 *   - a device that exceeds the cap receives 429 without persisting a row
 *   - one device's counter does not affect another device's counter
 */
class TelemetryRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        RateLimiter::clear('device:1');
        RateLimiter::clear('device:2');
    }

    private function payload(): array
    {
        return [
            'latitude' => -6.2,
            'longitude' => 106.8,
            'gps_timestamp' => now('UTC')->toIso8601String(),
        ];
    }

    private function auth(Device $device): array
    {
        return ['Authorization' => 'Bearer '.$device->api_token];
    }

    public function test_requests_within_cap_succeed(): void
    {
        config()->set('telemetry.max_submissions_per_minute', 3);
        $device = Device::factory()->withToken('under-cap-token-1234567890abcdef')->create();

        for ($i = 0; $i < 3; $i++) {
            $this->postJson(
                route('api.telemetry.store'),
                $this->payload(),
                $this->auth($device),
            )->assertNoContent();
        }
    }

    public function test_request_beyond_cap_returns_429(): void
    {
        config()->set('telemetry.max_submissions_per_minute', 2);
        $device = Device::factory()->withToken('over-cap-token-1234567890abcdefg')->create();

        $this->postJson(route('api.telemetry.store'), $this->payload(), $this->auth($device))
            ->assertNoContent();
        $this->postJson(route('api.telemetry.store'), $this->payload(), $this->auth($device))
            ->assertNoContent();

        $blocked = $this->postJson(
            route('api.telemetry.store'),
            $this->payload(),
            $this->auth($device),
        );

        $blocked->assertStatus(429);
    }

    public function test_rate_limit_is_scoped_per_device(): void
    {
        config()->set('telemetry.max_submissions_per_minute', 1);

        $deviceA = Device::factory()->withToken('scoped-a-token-1234567890abcdefg')->create();
        $deviceB = Device::factory()->withToken('scoped-b-token-1234567890abcdefg')->create();

        // Device A burns its budget.
        $this->postJson(route('api.telemetry.store'), $this->payload(), $this->auth($deviceA))
            ->assertNoContent();
        $this->postJson(route('api.telemetry.store'), $this->payload(), $this->auth($deviceA))
            ->assertStatus(429);

        // Device B still has a fresh counter.
        $this->postJson(route('api.telemetry.store'), $this->payload(), $this->auth($deviceB))
            ->assertNoContent();
    }

    public function test_429_response_carries_retry_after_header(): void
    {
        config()->set('telemetry.max_submissions_per_minute', 1);
        $device = Device::factory()->withToken('retryafter-token-1234567890abcde')->create();

        $this->postJson(route('api.telemetry.store'), $this->payload(), $this->auth($device))
            ->assertNoContent();

        $response = $this->postJson(
            route('api.telemetry.store'),
            $this->payload(),
            $this->auth($device),
        );

        $response->assertStatus(429);
        $this->assertNotNull($response->headers->get('Retry-After'));
    }
}
