<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Domain\Delivery\DeliveryStatus;
use App\Http\Controllers\TrackingController;
use App\Models\Delivery;
use App\Models\Device;
use App\Models\TelemetryRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Feature test for `GET /track/telemetry/latest` (route name
 * `tracking.telemetry.latest`).
 *
 * Enforces the AR-57 customer projection rules:
 *   - Only session-scoped access; no Laravel auth involved.
 *   - Response never leaks `speed_kmh`, `heading_degrees`, or
 *     `gps_timestamp`; the customer sees `latitude`, `longitude`,
 *     `received_at` only.
 *   - `latest` is `null` unless the delivery is currently `in_transit`
 *     (regardless of whether telemetry rows exist).
 *   - Missing/invalid session returns JSON 401 rather than a redirect,
 *     because the JS client uses fetch() and interprets non-2xx as
 *     "hold the last-known marker".
 */
class TrackingTelemetryLatestTest extends TestCase
{
    use RefreshDatabase;

    private function inSession(Delivery $delivery): self
    {
        return $this->withSession([TrackingController::SESSION_KEY => $delivery->id]);
    }

    private function seedLatest(Delivery $delivery, array $overrides = []): TelemetryRecord
    {
        $device = Device::factory()->create();

        return TelemetryRecord::factory()->create(array_replace([
            'device_id' => $device->id,
            'delivery_id' => $delivery->id,
            'latitude' => -6.20,
            'longitude' => 106.80,
            'speed_kmh' => 55.5,
            'heading_degrees' => 200.0,
            'gps_timestamp' => Carbon::parse('2026-07-30 10:00:00', 'UTC'),
            'received_at' => Carbon::parse('2026-07-30 10:00:03', 'UTC'),
        ], $overrides));
    }

    public function test_missing_session_returns_401_json(): void
    {
        $this->getJson(route('tracking.telemetry.latest'))
            ->assertStatus(401)
            ->assertJsonStructure(['message']);
    }

    public function test_session_pointing_to_missing_delivery_returns_401(): void
    {
        $this->withSession([TrackingController::SESSION_KEY => 999_999])
            ->getJson(route('tracking.telemetry.latest'))
            ->assertStatus(401);
    }

    public function test_in_transit_delivery_returns_lat_lng_and_received_at(): void
    {
        $delivery = Delivery::factory()->inTransit()->create();
        $this->seedLatest($delivery);

        $response = $this->inSession($delivery)
            ->getJson(route('tracking.telemetry.latest'));

        $response->assertOk();
        $response->assertJsonPath('delivery_id', $delivery->id);
        $response->assertJsonPath('status', 'in_transit');
        $response->assertJsonPath('latest.latitude', -6.20);
        $response->assertJsonPath('latest.longitude', 106.80);
        $response->assertJsonPath('latest.received_at', '2026-07-30T10:00:03Z');
    }

    public function test_in_transit_response_omits_speed_heading_and_gps_timestamp(): void
    {
        $delivery = Delivery::factory()->inTransit()->create();
        $this->seedLatest($delivery);

        $response = $this->inSession($delivery)
            ->getJson(route('tracking.telemetry.latest'));

        $response->assertOk();
        $json = $response->json('latest');
        $this->assertIsArray($json);
        $this->assertArrayNotHasKey('speed_kmh', $json);
        $this->assertArrayNotHasKey('heading_degrees', $json);
        $this->assertArrayNotHasKey('gps_timestamp', $json);
    }

    public function test_scheduled_delivery_returns_null_latest_even_with_rows(): void
    {
        $delivery = Delivery::factory()->scheduled()->create();
        $this->seedLatest($delivery);

        $response = $this->inSession($delivery)
            ->getJson(route('tracking.telemetry.latest'));

        $response->assertOk();
        $response->assertJsonPath('status', 'scheduled');
        $response->assertJsonPath('latest', null);
    }

    public function test_delivered_delivery_returns_null_latest(): void
    {
        $delivery = Delivery::factory()->inTransit()->create();
        $this->seedLatest($delivery);
        $delivery->status = DeliveryStatus::Delivered;
        $delivery->delivered_at = Carbon::now('UTC');
        $delivery->save();

        $response = $this->inSession($delivery)
            ->getJson(route('tracking.telemetry.latest'));

        $response->assertOk();
        $response->assertJsonPath('status', 'delivered');
        $response->assertJsonPath('latest', null);
    }

    public function test_cancelled_delivery_returns_null_latest(): void
    {
        $delivery = Delivery::factory()->cancelledFromScheduled()->create();

        $response = $this->inSession($delivery)
            ->getJson(route('tracking.telemetry.latest'));

        $response->assertOk();
        $response->assertJsonPath('latest', null);
    }

    public function test_in_transit_without_records_returns_null_latest(): void
    {
        $delivery = Delivery::factory()->inTransit()->create();

        $response = $this->inSession($delivery)
            ->getJson(route('tracking.telemetry.latest'));

        $response->assertOk();
        $response->assertJsonPath('status', 'in_transit');
        $response->assertJsonPath('latest', null);
    }

    public function test_uses_most_recent_row_by_received_at(): void
    {
        $delivery = Delivery::factory()->inTransit()->create();
        $device = Device::factory()->create();

        TelemetryRecord::factory()->create([
            'device_id' => $device->id,
            'delivery_id' => $delivery->id,
            'latitude' => -6.20,
            'longitude' => 106.80,
            'received_at' => Carbon::parse('2026-07-30 10:00:00', 'UTC'),
        ]);
        TelemetryRecord::factory()->create([
            'device_id' => $device->id,
            'delivery_id' => $delivery->id,
            'latitude' => -6.25,
            'longitude' => 106.85,
            'received_at' => Carbon::parse('2026-07-30 10:01:00', 'UTC'),
        ]);

        $response = $this->inSession($delivery)
            ->getJson(route('tracking.telemetry.latest'));

        $response->assertOk();
        $response->assertJsonPath('latest.latitude', -6.25);
        $response->assertJsonPath('latest.received_at', '2026-07-30T10:01:00Z');
    }

    public function test_throttle_bucket_returns_429_after_sixty_first_request(): void
    {
        $delivery = Delivery::factory()->inTransit()->create();

        for ($i = 0; $i < 60; $i++) {
            $this->inSession($delivery)
                ->getJson(route('tracking.telemetry.latest'))
                ->assertOk();
        }

        $this->inSession($delivery)
            ->getJson(route('tracking.telemetry.latest'))
            ->assertStatus(429);
    }

    public function test_does_not_require_an_authenticated_user(): void
    {
        // The customer surface is session-scoped, not user-scoped. This
        // test guards against accidentally re-adding `auth` middleware.
        $delivery = Delivery::factory()->inTransit()->create();
        $this->seedLatest($delivery);

        $response = $this->inSession($delivery)
            ->getJson(route('tracking.telemetry.latest'));

        $response->assertOk();
        $this->assertGuest();
    }
}
