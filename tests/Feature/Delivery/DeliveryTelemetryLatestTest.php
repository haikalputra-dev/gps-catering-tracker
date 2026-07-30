<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Domain\Delivery\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Device;
use App\Models\TelemetryRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Feature test for `GET /deliveries/{delivery}/telemetry/latest`
 * (route name `deliveries.telemetry.latest`).
 *
 * Verifies:
 *   - Role/auth wiring via `auth`, `active`, `role:owner,staff,courier`.
 *   - Payload shape from {@see \App\Domain\Telemetry\LatestTelemetryProvider}.
 *   - The AR-57 courier-ownership guard (`403` for couriers viewing a
 *     delivery not assigned to them).
 *   - Throttle bucket `60,1` returns 429 after the 61st request.
 */
class DeliveryTelemetryLatestTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        // The route lives on the `web` stack, so unauthenticated hits
        // redirect through `auth` rather than returning JSON 401.
        // The JS client uses fetch() with credentials and interprets
        // any non-2xx as "hold the last-known position".
        $delivery = Delivery::factory()->inTransit()->create();

        $this->get(route('deliveries.telemetry.latest', $delivery))
            ->assertRedirect('/login');
    }

    public function test_inactive_user_is_rejected(): void
    {
        $delivery = Delivery::factory()->inTransit()->create();
        $user = User::factory()->staff()->inactive()->create();

        $response = $this->actingAs($user)
            ->get(route('deliveries.telemetry.latest', $delivery));

        // The `active` middleware boots inactive sessions; the exact
        // redirect target is not the point of this test — just that
        // the request never reaches the controller.
        $this->assertNotEquals(200, $response->status());
        $this->assertContains($response->status(), [302, 403]);
    }

    public function test_owner_can_poll_any_delivery_and_receive_full_row(): void
    {
        $delivery = Delivery::factory()->inTransit()->create();
        $device = Device::factory()->create();
        TelemetryRecord::factory()->create([
            'device_id' => $device->id,
            'delivery_id' => $delivery->id,
            'latitude' => -6.20,
            'longitude' => 106.80,
            'speed_kmh' => 40.5,
            'heading_degrees' => 128.0,
            'gps_timestamp' => Carbon::parse('2026-07-30 10:00:00', 'UTC'),
            'received_at' => Carbon::parse('2026-07-30 10:00:03', 'UTC'),
        ]);
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner)
            ->getJson(route('deliveries.telemetry.latest', $delivery));

        $response->assertOk();
        $response->assertJsonStructure([
            'delivery_id',
            'status',
            'latest' => [
                'latitude',
                'longitude',
                'speed_kmh',
                'heading_degrees',
                'gps_timestamp',
                'received_at',
            ],
        ]);
        $response->assertJsonPath('delivery_id', $delivery->id);
        $response->assertJsonPath('status', 'in_transit');
        $response->assertJsonPath('latest.speed_kmh', 40.5);
        $response->assertJsonPath('latest.gps_timestamp', '2026-07-30T10:00:00Z');
    }

    public function test_staff_can_poll_any_delivery(): void
    {
        $delivery = Delivery::factory()->inTransit()->create();
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->getJson(route('deliveries.telemetry.latest', $delivery))
            ->assertOk()
            ->assertJsonPath('latest', null);
    }

    public function test_returns_null_latest_when_no_records_exist(): void
    {
        $delivery = Delivery::factory()->scheduled()->create();
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner)
            ->getJson(route('deliveries.telemetry.latest', $delivery));

        $response->assertOk();
        $response->assertJsonPath('status', 'scheduled');
        $response->assertJsonPath('latest', null);
    }

    public function test_assigned_courier_can_poll_their_own_delivery(): void
    {
        $courier = User::factory()->courier()->create();
        $delivery = Delivery::factory()->inTransit()->create([
            'courier_id' => $courier->id,
        ]);

        $this->actingAs($courier)
            ->getJson(route('deliveries.telemetry.latest', $delivery))
            ->assertOk()
            ->assertJsonPath('delivery_id', $delivery->id);
    }

    public function test_courier_polling_another_couriers_delivery_gets_403(): void
    {
        $mine = User::factory()->courier()->create();
        $theirs = User::factory()->courier()->create();
        $delivery = Delivery::factory()->inTransit()->create([
            'courier_id' => $theirs->id,
        ]);

        $this->actingAs($mine)
            ->getJson(route('deliveries.telemetry.latest', $delivery))
            ->assertStatus(403);
    }

    public function test_unknown_delivery_returns_404(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->getJson('/deliveries/999999/telemetry/latest')
            ->assertStatus(404);
    }

    public function test_throttle_bucket_returns_429_after_sixty_first_request(): void
    {
        $delivery = Delivery::factory()->inTransit()->create();
        $owner = User::factory()->owner()->create();

        for ($i = 0; $i < 60; $i++) {
            $this->actingAs($owner)
                ->getJson(route('deliveries.telemetry.latest', $delivery))
                ->assertOk();
        }

        $this->actingAs($owner)
            ->getJson(route('deliveries.telemetry.latest', $delivery))
            ->assertStatus(429);
    }

    public function test_delivered_delivery_still_returns_last_row_for_staff(): void
    {
        // Staff surface has no in_transit gate (unlike customer): once the
        // final row is written, it must remain visible for retrospective
        // review even after the delivery closes.
        $delivery = Delivery::factory()->inTransit()->create();
        $device = Device::factory()->create();
        TelemetryRecord::factory()->create([
            'device_id' => $device->id,
            'delivery_id' => $delivery->id,
            'latitude' => -6.25,
            'longitude' => 106.85,
        ]);

        $delivery->status = DeliveryStatus::Delivered;
        $delivery->delivered_at = Carbon::now('UTC');
        $delivery->save();

        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->getJson(route('deliveries.telemetry.latest', $delivery))
            ->assertOk()
            ->assertJsonPath('status', 'delivered')
            ->assertJsonPath('latest.latitude', -6.25);
    }
}
