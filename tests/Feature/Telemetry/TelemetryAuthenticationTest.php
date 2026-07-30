<?php

declare(strict_types=1);

namespace Tests\Feature\Telemetry;

use App\Models\Device;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Focused coverage of the `device.auth` middleware behaviour for the
 * `POST /api/telemetry` endpoint.
 *
 * The middleware must respond `401` with a generic message for every
 * form of failure (missing header, wrong scheme, unknown token,
 * inactive device) so it cannot be used as an oracle to enumerate
 * device identifiers or tokens. AR-47 revised requires `hash_equals`
 * on the final comparison; that's covered indirectly here — as long
 * as the correct token succeeds and near-miss tokens all fail with
 * the same status.
 */
class TelemetryAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return [
            'latitude' => -6.2,
            'longitude' => 106.8,
            'gps_timestamp' => now()->toIso8601String(),
        ];
    }

    public function test_missing_authorization_header_returns_401(): void
    {
        $response = $this->postJson(route('api.telemetry.store'), $this->payload());

        $response->assertUnauthorized();
        $response->assertJsonPath('message', 'Invalid device token.');
    }

    public function test_non_bearer_scheme_is_rejected(): void
    {
        $device = Device::factory()->withToken('good-token-1234567890abcdefghij')->create();

        $response = $this->postJson(route('api.telemetry.store'), $this->payload(), [
            'Authorization' => 'Basic '.base64_encode('user:'.$device->api_token),
        ]);

        $response->assertUnauthorized();
    }

    public function test_empty_bearer_token_is_rejected(): void
    {
        $response = $this->postJson(route('api.telemetry.store'), $this->payload(), [
            'Authorization' => 'Bearer ',
        ]);

        $response->assertUnauthorized();
    }

    public function test_unknown_token_returns_401(): void
    {
        Device::factory()->withToken('real-token-1234567890abcdefghij')->create();

        $response = $this->postJson(route('api.telemetry.store'), $this->payload(), [
            'Authorization' => 'Bearer wrong-token-1234567890abcdefgh',
        ]);

        $response->assertUnauthorized();
        $response->assertJsonPath('message', 'Invalid device token.');
    }

    public function test_inactive_device_returns_401_even_with_correct_token(): void
    {
        $device = Device::factory()
            ->inactive()
            ->withToken('inactive-token-1234567890abcdefgh')
            ->create();

        $response = $this->postJson(route('api.telemetry.store'), $this->payload(), [
            'Authorization' => 'Bearer '.$device->api_token,
        ]);

        $response->assertUnauthorized();
    }

    public function test_valid_bearer_token_is_accepted(): void
    {
        $device = Device::factory()
            ->withToken('valid-token-1234567890abcdefghij')
            ->create();

        // No assignment / no delivery: ingester will return null and the
        // controller returns 204. We only care that auth succeeded.
        $response = $this->postJson(route('api.telemetry.store'), $this->payload(), [
            'Authorization' => 'Bearer '.$device->api_token,
        ]);

        $response->assertNoContent();
    }

    public function test_bearer_scheme_matching_is_case_insensitive(): void
    {
        $device = Device::factory()
            ->withToken('case-token-1234567890abcdefghij')
            ->create();

        $response = $this->postJson(route('api.telemetry.store'), $this->payload(), [
            'Authorization' => 'bearer '.$device->api_token,
        ]);

        $response->assertNoContent();
    }

    public function test_case_mismatched_token_is_rejected(): void
    {
        $device = Device::factory()
            ->withToken('MixedCaseToken1234567890ABCDefgh')
            ->create();

        $response = $this->postJson(route('api.telemetry.store'), $this->payload(), [
            'Authorization' => 'Bearer '.strtolower($device->api_token),
        ]);

        $response->assertUnauthorized();
    }
}
