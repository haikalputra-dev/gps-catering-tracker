<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Kitchen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Packet 09 — Route access matrix for the two new endpoints.
 *
 * `POST /deliveries/{delivery}/dispatch`      — role:courier only
 * `POST /deliveries/{delivery}/mark-delivered`— role:courier only
 *
 * These tests lock the wiring in routes/web.php so that a future
 * refactor (renaming middleware, moving the group, etc.) cannot
 * silently loosen role access. Domain-level assertions (state
 * machine, actor identity, timestamp ordering) live in the unit
 * tests for DeliveryDispatcher and DeliveryCompleter — here we
 * only care about the HTTP boundary.
 *
 * Convention:
 *   - "forbidden" = the middleware layer rejects the caller (403).
 *   - "redirected with session error" = the FormRequest or
 *     controller redirected the caller to a role-appropriate
 *     surface with a flash error. We assert the concrete outcome
 *     rather than a generic status code to catch drift.
 */
class DeliveryRouteAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a `scheduled` delivery ready to be dispatched by the
     * given courier. Used by dispatch-endpoint tests.
     */
    private function scheduledFor(User $courier): Delivery
    {
        return Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create(['courier_id' => $courier->id]);
    }

    /**
     * Build an `in_transit` delivery ready to be completed by the
     * given courier. Used by mark-delivered-endpoint tests.
     */
    private function inTransitFor(User $courier): Delivery
    {
        return Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->inTransit()
            ->create(['courier_id' => $courier->id]);
    }

    // ------------------------------------------------------------
    //  POST /deliveries/{delivery}/dispatch
    // ------------------------------------------------------------

    public function test_dispatch_route_rejects_guest_with_redirect_to_login(): void
    {
        // Auth middleware fires before role middleware; unauthenticated
        // POSTs to a role-protected route must land on the login page.
        $courier = User::factory()->courier()->create();
        $delivery = $this->scheduledFor($courier);

        $this->post("/deliveries/{$delivery->id}/dispatch")
            ->assertRedirect('/login');
    }

    public function test_dispatch_route_forbids_owner(): void
    {
        // Owner has broad control over the office side of the app
        // but MUST NOT be able to fire the courier's physical
        // "leaving the kitchen" event. That's the courier's action
        // to take (AR-35, AR-41).
        $courier = User::factory()->courier()->create();
        $delivery = $this->scheduledFor($courier);
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->post("/deliveries/{$delivery->id}/dispatch")
            ->assertForbidden();
    }

    public function test_dispatch_route_forbids_staff(): void
    {
        // Same rationale as owner — staff schedules and assigns,
        // but the physical dispatch is initiated by the courier
        // themselves. This test defends the role split.
        $courier = User::factory()->courier()->create();
        $delivery = $this->scheduledFor($courier);
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->post("/deliveries/{$delivery->id}/dispatch")
            ->assertForbidden();
    }

    public function test_dispatch_route_forbids_inactive_courier(): void
    {
        // Inactive couriers are logged out at auth time in most
        // flows, but the middleware chain should still reject them
        // if a session lingers. `inactive()` couriers get is_active
        // set to false; we treat them as guests for role checks.
        $active = User::factory()->courier()->create();
        $delivery = $this->scheduledFor($active);
        $inactive = User::factory()->courier()->inactive()->create();

        $response = $this->actingAs($inactive)
            ->post("/deliveries/{$delivery->id}/dispatch");

        // Inactive users are treated as guests by the auth layer,
        // so they get redirected to /login rather than a 403.
        $response->assertRedirect('/login');
    }

    public function test_dispatch_route_allows_assigned_courier(): void
    {
        // Happy path at the HTTP layer: an assigned courier POSTs
        // to /dispatch and lands back on the courier dashboard
        // with a success flash. The DB-side state change is
        // covered by DeliveryDispatcherTest — here we assert only
        // the routing/redirect contract.
        $courier = User::factory()->courier()->create();
        $delivery = $this->scheduledFor($courier);

        $response = $this->actingAs($courier)
            ->post("/deliveries/{$delivery->id}/dispatch");

        $response->assertRedirect(route('deliveries.show', $delivery));
        $response->assertSessionHas('status');
    }

    // ------------------------------------------------------------
    //  POST /deliveries/{delivery}/mark-delivered
    // ------------------------------------------------------------

    public function test_mark_delivered_route_rejects_guest_with_redirect_to_login(): void
    {
        // Unauthenticated POST -> /login. Same reasoning as dispatch.
        $courier = User::factory()->courier()->create();
        $delivery = $this->inTransitFor($courier);

        $this->post("/deliveries/{$delivery->id}/mark-delivered")
            ->assertRedirect('/login');
    }

    public function test_mark_delivered_route_forbids_owner(): void
    {
        // The physical "package handed to customer" event belongs
        // to the courier. Owner cannot fire it from the office UI.
        $courier = User::factory()->courier()->create();
        $delivery = $this->inTransitFor($courier);
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)
            ->post("/deliveries/{$delivery->id}/mark-delivered")
            ->assertForbidden();
    }

    public function test_mark_delivered_route_forbids_staff(): void
    {
        // Same as owner. Staff has scheduling authority but not
        // physical-completion authority.
        $courier = User::factory()->courier()->create();
        $delivery = $this->inTransitFor($courier);
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)
            ->post("/deliveries/{$delivery->id}/mark-delivered")
            ->assertForbidden();
    }

    public function test_mark_delivered_route_forbids_inactive_courier(): void
    {
        // Inactive couriers are treated as guests by the auth
        // pipeline (same behaviour as dispatch above).
        $active = User::factory()->courier()->create();
        $delivery = $this->inTransitFor($active);
        $inactive = User::factory()->courier()->inactive()->create();

        $this->actingAs($inactive)
            ->post("/deliveries/{$delivery->id}/mark-delivered")
            ->assertRedirect('/login');
    }

    public function test_mark_delivered_route_allows_assigned_courier(): void
    {
        // Happy path at the HTTP layer. The DB assertion for the
        // state transition and `delivered_at` stamp is in
        // DeliveryCompleterTest.
        $courier = User::factory()->courier()->create();
        $delivery = $this->inTransitFor($courier);

        $response = $this->actingAs($courier)
            ->post("/deliveries/{$delivery->id}/mark-delivered");

        $response->assertRedirect(route('deliveries.show', $delivery));
        $response->assertSessionHas('status');
    }
}
