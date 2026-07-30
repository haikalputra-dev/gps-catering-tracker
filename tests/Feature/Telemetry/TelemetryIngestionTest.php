<?php

declare(strict_types=1);

namespace Tests\Feature\Telemetry;

use App\Domain\Device\DeviceAssignmentService;
use App\Models\Delivery;
use App\Models\Device;
use App\Models\TelemetryRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Verifies the ingest behaviour of `POST /api/telemetry` end-to-end:
 * response codes, payload validation, persistence, and the
 * accept-and-discard branch defined by AR-51.
 *
 * Auth failure modes live in {@see TelemetryAuthenticationTest}; here
 * every request presents a valid Bearer token so we can focus on
 * business behaviour.
 */
class TelemetryIngestionTest extends TestCase
{
    use RefreshDatabase;

    private function activeDevice(string $token = 'ingest-token-1234567890abcdefghij'): Device
    {
        return Device::factory()->withToken($token)->create();
    }

    private function auth(Device $device): array
    {
        return ['Authorization' => 'Bearer '.$device->api_token];
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'latitude' => -6.2000000,
            'longitude' => 106.8166667,
            'speed_kmh' => 42.5,
            'heading_degrees' => 90.0,
            'gps_timestamp' => Carbon::now('UTC')->toIso8601String(),
        ], $overrides);
    }

    private function bind(Device $device, User $courier): void
    {
        $owner = User::factory()->owner()->create();
        app(DeviceAssignmentService::class)->assign($device, $courier, $owner);
    }

    public function test_accepted_submission_with_active_delivery_persists_a_record(): void
    {
        $device = $this->activeDevice();
        $courier = User::factory()->courier()->create();
        $this->bind($device, $courier);
        $delivery = Delivery::factory()->inTransit()->create([
            'courier_id' => $courier->id,
        ]);

        $response = $this->postJson(
            route('api.telemetry.store'),
            $this->payload(),
            $this->auth($device),
        );

        $response->assertNoContent();
        $this->assertSame(1, TelemetryRecord::query()->count());

        $record = TelemetryRecord::query()->firstOrFail();
        $this->assertSame($device->id, (int) $record->device_id);
        $this->assertSame($delivery->id, (int) $record->delivery_id);
        $this->assertNotNull($record->received_at);
    }

    public function test_accepted_submission_bumps_last_seen_at_even_when_discarded(): void
    {
        $device = $this->activeDevice();
        $this->assertNull($device->last_seen_at);

        $response = $this->postJson(
            route('api.telemetry.store'),
            $this->payload(),
            $this->auth($device),
        );

        $response->assertNoContent();
        $this->assertSame(0, TelemetryRecord::query()->count());
        $device->refresh();
        $this->assertNotNull($device->last_seen_at);
    }

    public function test_discards_when_bound_courier_has_no_active_delivery(): void
    {
        $device = $this->activeDevice();
        $courier = User::factory()->courier()->create();
        $this->bind($device, $courier);

        $response = $this->postJson(
            route('api.telemetry.store'),
            $this->payload(),
            $this->auth($device),
        );

        $response->assertNoContent();
        $this->assertSame(0, TelemetryRecord::query()->count());
    }

    public function test_validation_error_returns_422_json(): void
    {
        $device = $this->activeDevice();

        $response = $this->postJson(
            route('api.telemetry.store'),
            $this->payload(['latitude' => 200]),
            $this->auth($device),
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['latitude']);
        $this->assertSame(0, TelemetryRecord::query()->count());
    }

    public function test_missing_required_field_returns_422(): void
    {
        $device = $this->activeDevice();
        $payload = $this->payload();
        unset($payload['gps_timestamp']);

        $response = $this->postJson(
            route('api.telemetry.store'),
            $payload,
            $this->auth($device),
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['gps_timestamp']);
    }

    public function test_malformed_gps_timestamp_returns_422(): void
    {
        $device = $this->activeDevice();

        $response = $this->postJson(
            route('api.telemetry.store'),
            $this->payload(['gps_timestamp' => 'not-a-date']),
            $this->auth($device),
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['gps_timestamp']);
    }

    public function test_future_gps_timestamp_beyond_skew_returns_422(): void
    {
        $device = $this->activeDevice();

        $response = $this->postJson(
            route('api.telemetry.store'),
            $this->payload([
                'gps_timestamp' => Carbon::now('UTC')->addHour()->toIso8601String(),
            ]),
            $this->auth($device),
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['gps_timestamp']);
    }

    public function test_ancient_gps_timestamp_returns_422(): void
    {
        $device = $this->activeDevice();

        $response = $this->postJson(
            route('api.telemetry.store'),
            $this->payload([
                'gps_timestamp' => Carbon::now('UTC')->subDays(3)->toIso8601String(),
            ]),
            $this->auth($device),
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['gps_timestamp']);
    }

    public function test_optional_fields_can_be_omitted(): void
    {
        $device = $this->activeDevice();
        $courier = User::factory()->courier()->create();
        $this->bind($device, $courier);
        Delivery::factory()->inTransit()->create(['courier_id' => $courier->id]);

        $response = $this->postJson(
            route('api.telemetry.store'),
            [
                'latitude' => -6.21,
                'longitude' => 106.82,
                'gps_timestamp' => Carbon::now('UTC')->toIso8601String(),
            ],
            $this->auth($device),
        );

        $response->assertNoContent();
        $record = TelemetryRecord::query()->firstOrFail();
        $this->assertNull($record->speed_kmh);
        $this->assertNull($record->heading_degrees);
    }

    public function test_latitude_and_longitude_bounds_are_enforced(): void
    {
        $device = $this->activeDevice();

        $response = $this->postJson(
            route('api.telemetry.store'),
            $this->payload(['longitude' => -181]),
            $this->auth($device),
        );

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['longitude']);
    }
}
