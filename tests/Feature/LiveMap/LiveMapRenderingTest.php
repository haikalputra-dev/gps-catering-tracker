<?php

declare(strict_types=1);

namespace Tests\Feature\LiveMap;

use App\Domain\Delivery\DeliveryStatus;
use App\Http\Controllers\TrackingController;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * View-integration tests for the Packet 12 live-map cards.
 *
 * Locks the AR-55 / AR-57 status gates:
 *   - Internal delivery show page renders `#delivery-live-map` for
 *     `scheduled` and `in_transit`, and NEVER for `delivered`,
 *     `cancelled`, or `draft`.
 *   - Customer tracking status renders `#tracking-live-map` only when
 *     the delivery is `in_transit`.
 *
 * The tests deliberately assert on stable DOM ids and on the presence
 * of `data-endpoint` / `data-live-map` hooks so a future markup
 * refactor cannot silently drop the polling wiring.
 */
class LiveMapRenderingTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsOwner(): User
    {
        $owner = User::factory()->owner()->create();
        $this->actingAs($owner);
        return $owner;
    }

    // ----------------------------------------------------------------
    //  Internal delivery show page (owner/staff/courier surface)
    // ----------------------------------------------------------------

    public function test_delivery_show_renders_live_map_for_scheduled_delivery(): void
    {
        $this->actingAsOwner();
        $delivery = Delivery::factory()->scheduled()->create();

        $response = $this->get(route('deliveries.show', $delivery));

        $response->assertOk();
        $response->assertSee('id="delivery-live-map"', escape: false);
        $response->assertSee('data-live-map', escape: false);
        $response->assertSee(
            'data-endpoint="'.route('deliveries.telemetry.latest', $delivery).'"',
            escape: false,
        );
    }

    public function test_delivery_show_renders_live_map_for_in_transit_delivery(): void
    {
        $this->actingAsOwner();
        $delivery = Delivery::factory()->inTransit()->create();

        $response = $this->get(route('deliveries.show', $delivery));

        $response->assertOk();
        $response->assertSee('id="delivery-live-map"', escape: false);
        $response->assertSee('data-live-map', escape: false);
    }

    public function test_delivery_show_hides_live_map_for_delivered_delivery(): void
    {
        $this->actingAsOwner();
        $delivery = Delivery::factory()->delivered()->create();

        $response = $this->get(route('deliveries.show', $delivery));

        $response->assertOk();
        $response->assertDontSee('id="delivery-live-map"', escape: false);
        $response->assertDontSee('data-live-map', escape: false);
    }

    public function test_delivery_show_hides_live_map_for_cancelled_delivery(): void
    {
        $this->actingAsOwner();
        $delivery = Delivery::factory()->cancelledFromScheduled()->create();

        $response = $this->get(route('deliveries.show', $delivery));

        $response->assertOk();
        $response->assertDontSee('id="delivery-live-map"', escape: false);
    }

    public function test_delivery_show_hides_live_map_for_draft_delivery(): void
    {
        $this->actingAsOwner();
        // Draft coordinates may be null; that is exactly why the map
        // must not render before scheduling.
        $delivery = Delivery::factory()->create([
            'status' => DeliveryStatus::Draft->value,
        ]);

        $response = $this->get(route('deliveries.show', $delivery));

        $response->assertOk();
        $response->assertDontSee('id="delivery-live-map"', escape: false);
    }

    public function test_delivery_show_live_map_carries_polling_interval(): void
    {
        $this->actingAsOwner();
        config()->set('telemetry.polling_interval_ms', 4000);
        $delivery = Delivery::factory()->inTransit()->create();

        $response = $this->get(route('deliveries.show', $delivery));

        $response->assertOk();
        $response->assertSee('data-interval="4000"', escape: false);
    }

    // ----------------------------------------------------------------
    //  Public tracking status page (customer surface)
    // ----------------------------------------------------------------

    private function inTrackingSession(Delivery $delivery): self
    {
        return $this->withSession([TrackingController::SESSION_KEY => $delivery->id]);
    }

    public function test_tracking_status_renders_live_map_when_in_transit(): void
    {
        $delivery = Delivery::factory()->inTransit()->create();

        $response = $this->inTrackingSession($delivery)->get(route('tracking.status'));

        $response->assertOk();
        $response->assertSee('id="tracking-live-map"', escape: false);
        $response->assertSee('data-live-map', escape: false);
        $response->assertSee(
            'data-endpoint="'.route('tracking.telemetry.latest').'"',
            escape: false,
        );
    }

    public function test_tracking_status_hides_live_map_for_scheduled(): void
    {
        // AR-57: the customer surface never shows a map before dispatch;
        // there is no courier position to render, and the endpoint would
        // return `latest: null` even if it were polled.
        $delivery = Delivery::factory()->scheduled()->create();

        $response = $this->inTrackingSession($delivery)->get(route('tracking.status'));

        $response->assertOk();
        $response->assertDontSee('id="tracking-live-map"', escape: false);
    }

    public function test_tracking_status_hides_live_map_for_delivered(): void
    {
        $delivery = Delivery::factory()->delivered()->create();

        $response = $this->inTrackingSession($delivery)->get(route('tracking.status'));

        $response->assertOk();
        $response->assertDontSee('id="tracking-live-map"', escape: false);
    }

    public function test_tracking_status_hides_live_map_for_cancelled(): void
    {
        $delivery = Delivery::factory()->cancelledFromScheduled()->create();

        $response = $this->inTrackingSession($delivery)->get(route('tracking.status'));

        $response->assertOk();
        $response->assertDontSee('id="tracking-live-map"', escape: false);
    }
}
