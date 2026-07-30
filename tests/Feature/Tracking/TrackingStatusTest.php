<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Domain\Delivery\DeliveryStatus;
use App\Http\Controllers\TrackingController;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Kitchen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /track/status: session-scoped status page.
 *
 * Renders the timeline, kitchen and customer snapshots, distance and
 * fee, and (only for `in_transit`) courier contact info. Never renders
 * a live map, WebSocket bootstrap, or admin fields.
 */
class TrackingStatusTest extends TestCase
{
    use RefreshDatabase;

    private function inSession(Delivery $delivery): self
    {
        return $this->withSession([TrackingController::SESSION_KEY => $delivery->id]);
    }

    public function test_status_requires_a_session_delivery(): void
    {
        $response = $this->get(route('tracking.status'));

        $response->assertRedirect(route('tracking.form'));
        $response->assertSessionHas('info');
    }

    public function test_status_page_renders_kitchen_and_customer_snapshots(): void
    {
        $delivery = Delivery::factory()->scheduled()->create();

        $response = $this->inSession($delivery)->get(route('tracking.status'));

        $response->assertOk();
        $response->assertViewIs('tracking.status');
        $response->assertSee($delivery->receipt_number);
        $response->assertSee($delivery->kitchen_name);
        $response->assertSee($delivery->kitchen_address);
        $response->assertSee($delivery->customer_name);
        $response->assertSee($delivery->customer_address);
    }

    public function test_status_shows_distance_and_fee(): void
    {
        $delivery = Delivery::factory()->scheduled()->create([
            'distance_km' => '3.500',
            'fee_rupiah' => 17500,
        ]);

        $response = $this->inSession($delivery)->get(route('tracking.status'));

        $response->assertOk();
        $response->assertSee('3.50 km');
        $response->assertSee('Rp 17.500');
    }

    public function test_status_shows_status_badge_for_each_state(): void
    {
        $states = [
            ['scheduled', DeliveryStatus::Scheduled->label()],
            ['inTransit', DeliveryStatus::InTransit->label()],
            ['delivered', DeliveryStatus::Delivered->label()],
        ];

        foreach ($states as [$factoryState, $label]) {
            $delivery = Delivery::factory()->{$factoryState}()->create();
            $response = $this->inSession($delivery)->get(route('tracking.status'));

            $response->assertOk();
            $response->assertSee($label);
        }
    }

    public function test_courier_details_shown_only_when_in_transit(): void
    {
        $courier = User::factory()->courier()->create([
            'name' => 'Budi Courier',
            'phone' => '+628111222333',
        ]);

        $scheduled = Delivery::factory()->scheduled()->create([
            'courier_id' => $courier->id,
        ]);
        $response = $this->inSession($scheduled)->get(route('tracking.status'));
        $response->assertOk();
        $response->assertDontSee('Budi Courier');

        $inTransit = Delivery::factory()->inTransit()->create([
            'courier_id' => $courier->id,
        ]);
        $response = $this->inSession($inTransit)->get(route('tracking.status'));
        $response->assertOk();
        $response->assertSee('Budi Courier');
        $response->assertSee('+628111222333');
    }

    public function test_courier_details_hidden_when_delivered(): void
    {
        $courier = User::factory()->courier()->create([
            'name' => 'Budi Courier',
        ]);
        $delivered = Delivery::factory()->delivered()->create([
            'courier_id' => $courier->id,
        ]);

        $response = $this->inSession($delivered)->get(route('tracking.status'));

        $response->assertOk();
        $response->assertDontSee('Budi Courier');
    }

    public function test_cancelled_delivery_shows_cancellation_row(): void
    {
        $delivery = Delivery::factory()->cancelledFromScheduled()->create([
            'cancellation_reason' => 'Kitchen closed unexpectedly.',
        ]);

        $response = $this->inSession($delivery)->get(route('tracking.status'));

        $response->assertOk();
        $response->assertSee('Cancelled');
        $response->assertSee('Kitchen closed unexpectedly.');
    }

    public function test_timeline_renders_all_present_timestamps(): void
    {
        $delivery = Delivery::factory()->delivered()->create();

        $response = $this->inSession($delivery)->get(route('tracking.status'));

        $response->assertOk();
        $response->assertSee('Scheduled');
        $response->assertSee('Dispatched');
        $response->assertSee('Delivered');
    }

    public function test_status_does_not_expose_admin_fields(): void
    {
        $delivery = Delivery::factory()->scheduled()->create();

        $response = $this->inSession($delivery)->get(route('tracking.status'));

        $response->assertOk();
        // These labels are only ever rendered by the internal delivery
        // views and must not leak into the customer-facing page.
        $response->assertDontSee('created_by_user_id', escape: false);
        $response->assertDontSee('scheduled_by_user_id', escape: false);
        $response->assertDontSee('Internal notes');
        // The customer's own phone is not shown back to them; only the
        // last-four is what they authenticated with.
        $response->assertDontSee((string) $delivery->customer_phone);
    }

    public function test_status_page_has_no_live_map_or_websocket(): void
    {
        $delivery = Delivery::factory()->inTransit()->create();

        $response = $this->inSession($delivery)->get(route('tracking.status'));

        $response->assertOk();
        // Explicitly reject the primitives that would enable live map,
        // polling, or telemetry in the tracking view.
        $response->assertDontSee('leaflet', escape: false);
        $response->assertDontSee('Leaflet', escape: false);
        $response->assertDontSee('websocket', escape: false);
        $response->assertDontSee('WebSocket', escape: false);
        $response->assertDontSee('setInterval(', escape: false);
        $response->assertDontSee('pusher', escape: false);
        $response->assertDontSee('Pusher', escape: false);
    }

    public function test_status_clears_session_when_delivery_missing(): void
    {
        $response = $this
            ->withSession([TrackingController::SESSION_KEY => 999_999])
            ->get(route('tracking.status'));

        $response->assertRedirect(route('tracking.form'));
        $this->assertNull(session(TrackingController::SESSION_KEY));
    }

    public function test_status_rejects_draft_session_delivery(): void
    {
        $draft = Delivery::factory()->create([
            'status' => DeliveryStatus::Draft->value,
        ]);

        $response = $this->inSession($draft)->get(route('tracking.status'));

        $response->assertRedirect(route('tracking.form'));
        $this->assertNull(session(TrackingController::SESSION_KEY));
    }

    public function test_status_does_not_require_authentication(): void
    {
        // No user is acting; only the tracking session key is set.
        // Public tracking must work without any Laravel auth user.
        $delivery = Delivery::factory()->scheduled()->create();

        $response = $this->inSession($delivery)->get(route('tracking.status'));

        $response->assertOk();
        $this->assertGuest();
    }
}
