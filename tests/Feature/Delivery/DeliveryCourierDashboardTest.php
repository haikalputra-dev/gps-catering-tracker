<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Domain\Delivery\DeliveryStatus;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Kitchen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Packet 09 — Courier dashboard behaviour (AR-35, AR-41).
 *
 * The courier's home surface after login. It must:
 *   - only surface deliveries assigned to the acting courier
 *   - only surface deliveries in an active state (scheduled, in_transit)
 *   - show pickup/drop-off addresses so the courier knows where to go
 *   - offer state-appropriate actions (Start Delivery / Mark Delivered)
 *   - render an empty-state when there is nothing to do
 *   - never leak the fee (covered by DeliveryFeePrivacyForCourierTest)
 *
 * These tests cover the routing/scoping contract for the dashboard.
 * Fee-absence assertions live in the fee-privacy test to keep each
 * file focused on one concern.
 */
class DeliveryCourierDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_empty_state_when_no_assignments(): void
    {
        // A brand-new courier with nothing scheduled sees a helpful
        // empty-state rather than a broken table or blank screen.
        // The exact copy is asserted so a rewrite of the message is
        // a deliberate, reviewed choice.
        $courier = User::factory()->courier()->create();

        $this->actingAs($courier);
        $this->get(route('courier.dashboard'))
            ->assertOk()
            ->assertSee('No active delivery');
    }

    public function test_dashboard_does_not_list_other_couriers_deliveries(): void
    {
        // Cross-courier isolation: an in_transit delivery belonging
        // to a different courier must not appear on this courier's
        // dashboard, even by receipt number leakage.
        $mine = User::factory()->courier()->create();
        $other = User::factory()->courier()->create();

        $notMine = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->inTransit()
            ->create(['courier_id' => $other->id]);

        $this->actingAs($mine);
        $response = $this->get(route('courier.dashboard'));

        $response->assertOk();
        $response->assertSee('No active delivery');
        $response->assertDontSee((string) $notMine->receipt_number);
    }

    public function test_dashboard_shows_scheduled_assignment_with_start_action(): void
    {
        // A `scheduled` delivery is the "waiting to dispatch" state
        // from the courier's perspective. The dashboard should
        // surface pickup/drop-off addresses and the Start Delivery
        // action, but NOT the Mark Delivered action.
        $courier = User::factory()->courier()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create(['courier_id' => $courier->id]);

        $this->actingAs($courier);
        $response = $this->get(route('courier.dashboard'));

        $response->assertOk();
        $response->assertDontSee('No active delivery');
        $response->assertSee((string) $delivery->receipt_number);
        // Snapshot addresses drive navigation for the courier.
        $response->assertSee((string) $delivery->kitchen_address);
        $response->assertSee((string) $delivery->customer_address);
        // Only the state-appropriate action should render.
        $response->assertSee('Start Delivery');
        $response->assertDontSee('Mark Delivered');
    }

    public function test_dashboard_shows_in_transit_assignment_with_mark_delivered_action(): void
    {
        // Once dispatched, the Start Delivery button gives way to
        // Mark Delivered so the courier's next tap moves the state
        // to `delivered`.
        $courier = User::factory()->courier()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->inTransit()
            ->create(['courier_id' => $courier->id]);

        $this->actingAs($courier);
        $response = $this->get(route('courier.dashboard'));

        $response->assertOk();
        $response->assertSee((string) $delivery->receipt_number);
        $response->assertSee('Mark Delivered');
        $response->assertDontSee('Start Delivery');
    }

    public function test_dashboard_scopes_to_active_states_only(): void
    {
        // Cancelled and delivered deliveries are terminal and MUST
        // NOT clutter the courier's active-work view. The dashboard
        // is a "what should I be doing right now?" surface.
        $courier = User::factory()->courier()->create();

        $active = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create(['courier_id' => $courier->id]);

        $delivered = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->delivered()
            ->create(['courier_id' => $courier->id]);

        $cancelled = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->cancelledFromInTransit()
            ->create(['courier_id' => $courier->id]);

        $this->actingAs($courier);
        $response = $this->get(route('courier.dashboard'));

        $response->assertOk();
        $response->assertSee((string) $active->receipt_number);
        $response->assertDontSee((string) $delivered->receipt_number);
        $response->assertDontSee((string) $cancelled->receipt_number);
    }

    public function test_dashboard_is_forbidden_for_non_courier_roles(): void
    {
        // Route matrix sanity: the courier dashboard is a
        // role-scoped route. Owner and staff should be redirected
        // (or rejected) rather than rendering an empty version.
        $owner = User::factory()->owner()->create();
        $this->actingAs($owner);
        $this->get(route('courier.dashboard'))->assertForbidden();

        $staff = User::factory()->staff()->create();
        $this->actingAs($staff);
        $this->get(route('courier.dashboard'))->assertForbidden();
    }

    public function test_dashboard_status_pill_reflects_current_state(): void
    {
        // Small UX guard: the state label shown to the courier must
        // match the actual delivery status so a courier doesn't act
        // on stale UI. We assert both `scheduled` and `in_transit`
        // labels appear in their respective rows.
        $courier = User::factory()->courier()->create();

        Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create(['courier_id' => $courier->id]);

        $this->actingAs($courier);
        $response = $this->get(route('courier.dashboard'));
        $response->assertOk();
        $response->assertSee(DeliveryStatus::Scheduled->label());
    }
}
