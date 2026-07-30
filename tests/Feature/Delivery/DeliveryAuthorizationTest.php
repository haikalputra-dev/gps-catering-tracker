<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Kitchen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function draft(User $creator): Delivery
    {
        return Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->create(['created_by_user_id' => $creator->id]);
    }

    public function test_guest_redirected_from_index(): void
    {
        $this->get('/deliveries')->assertRedirect('/login');
    }

    public function test_guest_redirected_from_show(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = $this->draft($owner);

        $this->get("/deliveries/{$delivery->id}")->assertRedirect('/login');
    }

    public function test_owner_can_access_index(): void
    {
        $this->actingAs(User::factory()->owner()->create());
        $this->get('/deliveries')->assertOk();
    }

    public function test_staff_can_access_index(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $this->get('/deliveries')->assertOk();
    }

    public function test_courier_receives_403_from_index(): void
    {
        $this->actingAs(User::factory()->courier()->create());
        $this->get('/deliveries')->assertForbidden();
    }

    public function test_courier_receives_403_from_create(): void
    {
        $this->actingAs(User::factory()->courier()->create());
        $this->get('/deliveries/create')->assertForbidden();
    }

    public function test_courier_receives_403_from_store(): void
    {
        $this->actingAs(User::factory()->courier()->create());
        $this->post('/deliveries', [])->assertForbidden();
    }

    public function test_unassigned_courier_is_redirected_from_show(): void
    {
        // AR-41: couriers may only view deliveries assigned to them.
        // Access is not a hard 403; the controller redirects to the
        // courier dashboard with a status message so the courier gets
        // a coherent UX rather than a raw error page.
        $owner = User::factory()->owner()->create();
        $delivery = $this->draft($owner);

        $this->actingAs(User::factory()->courier()->create());
        $this->get("/deliveries/{$delivery->id}")
            ->assertRedirect(route('courier.dashboard'))
            ->assertSessionHasErrors('status');
    }

    public function test_assigned_courier_can_view_show(): void
    {
        // AR-41: the courier assigned to a delivery is permitted to
        // view its detail page. Fee-privacy handling (AR-40) is
        // exercised in the pricing/UI test suites, not here.
        $owner = User::factory()->owner()->create();
        $courier = User::factory()->courier()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create([
                'created_by_user_id' => $owner->id,
                'courier_id' => $courier->id,
            ]);

        $this->actingAs($courier);
        $this->get("/deliveries/{$delivery->id}")->assertOk();
    }

    public function test_courier_receives_403_from_edit(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = $this->draft($owner);

        $this->actingAs(User::factory()->courier()->create());
        $this->get("/deliveries/{$delivery->id}/edit")->assertForbidden();
    }

    public function test_courier_receives_403_from_update(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = $this->draft($owner);

        $this->actingAs(User::factory()->courier()->create());
        $this->put("/deliveries/{$delivery->id}", [])->assertForbidden();
    }

    public function test_courier_receives_403_from_schedule(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = $this->draft($owner);

        $this->actingAs(User::factory()->courier()->create());
        $this->post("/deliveries/{$delivery->id}/schedule")->assertForbidden();
    }

    public function test_courier_cannot_cancel_draft_delivery(): void
    {
        // AR-38: couriers may only cancel deliveries currently in
        // `in_transit` and assigned to them. A `draft` (or any state
        // other than `in_transit`) is not cancellable by a courier,
        // even if the courier is the assignee. The FormRequest gives
        // the courier a UX-friendly redirect + status error rather
        // than a raw 403, matching the pattern in show().
        $owner = User::factory()->owner()->create();
        $courier = User::factory()->courier()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->create([
                'created_by_user_id' => $owner->id,
                'courier_id' => $courier->id,
            ]);

        $this->actingAs($courier);
        $this->post("/deliveries/{$delivery->id}/cancel", [
            'cancellation_reason' => 'blocked route',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $this->assertSame('draft', $delivery->fresh()->status->value);
    }

    public function test_courier_cannot_cancel_someone_elses_in_transit(): void
    {
        // AR-38: a courier not assigned to the delivery is forbidden
        // from cancelling, even mid-route. Cross-courier interference
        // is explicitly out of scope (AR-41: no reassignment).
        $owner = User::factory()->owner()->create();
        $assignee = User::factory()->courier()->create();
        $intruder = User::factory()->courier()->create();

        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->inTransit()
            ->create([
                'created_by_user_id' => $owner->id,
                'courier_id' => $assignee->id,
            ]);

        $this->actingAs($intruder);
        $this->post("/deliveries/{$delivery->id}/cancel", [
            'cancellation_reason' => 'not my job',
        ])
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $this->assertSame('in_transit', $delivery->fresh()->status->value);
    }

    public function test_assigned_courier_can_cancel_own_in_transit(): void
    {
        // AR-38: mid-route cancellation is explicitly permitted for
        // the assigned courier when the delivery is `in_transit`.
        $owner = User::factory()->owner()->create();
        $courier = User::factory()->courier()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->inTransit()
            ->create([
                'created_by_user_id' => $owner->id,
                'courier_id' => $courier->id,
            ]);

        $this->actingAs($courier);
        $this->post("/deliveries/{$delivery->id}/cancel", [
            'cancellation_reason' => 'route blocked; customer notified',
        ])->assertRedirect();

        $this->assertSame('cancelled', $delivery->fresh()->status->value);
    }

    public function test_inactive_owner_session_is_rejected(): void
    {
        $user = User::factory()->owner()->create();
        $this->actingAs($user);
        $user->forceFill(['is_active' => false])->save();

        $this->get('/deliveries')->assertRedirect('/login');
        $this->assertGuest();
    }
}
