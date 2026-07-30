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
 * Packet 09 — Courier assignment preconditions at scheduling (AR-37).
 *
 * These tests target the seam between the delivery form (which stores
 * an optional `courier_id`) and the scheduler service (which promotes
 * the draft to `scheduled` only when a valid, active courier is
 * present and within the per-courier concurrency cap). The scheduler
 * is intentionally responsible for these guards so that any code path
 * that promotes a draft — HTTP, console, future queued jobs — inherits
 * the same invariants.
 */
class DeliveryCourierAssignmentTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a draft delivery owned by $creator, optionally with a
     * pre-assigned courier. We keep the helper explicit rather than
     * relying on the factory's `scheduled()` state because these
     * tests are specifically exercising the *precondition* checks,
     * not the terminal state.
     */
    private function draft(User $creator, ?User $courier = null): Delivery
    {
        return Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->create([
                'created_by_user_id' => $creator->id,
                'courier_id' => $courier?->id,
                'scheduled_at' => now()->addHours(2),
            ]);
    }

    public function test_schedule_promotes_draft_when_courier_is_valid(): void
    {
        // Happy path: an active courier is assigned; scheduling should
        // succeed and stamp `scheduled_by_user_id` on the delivery.
        $owner = User::factory()->owner()->create();
        $courier = User::factory()->courier()->create();
        $delivery = $this->draft($owner, $courier);

        $this->actingAs($owner);
        $this->post("/deliveries/{$delivery->id}/schedule")
            ->assertRedirect(route('deliveries.show', $delivery));

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Scheduled, $delivery->status);
        $this->assertSame($courier->id, $delivery->courier_id);
        $this->assertSame($owner->id, $delivery->scheduled_by_user_id);
    }

    public function test_schedule_rejects_draft_without_courier(): void
    {
        // AR-37: `courier_id` is nullable at draft but required for
        // promotion to `scheduled`. The scheduler raises a domain
        // exception which the controller translates to a status flash.
        $owner = User::factory()->owner()->create();
        $delivery = $this->draft($owner, courier: null);

        $this->actingAs($owner);
        $this->post("/deliveries/{$delivery->id}/schedule")
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Draft, $delivery->status);
        $this->assertNull($delivery->receipt_number);
    }

    public function test_schedule_rejects_draft_with_non_courier_role(): void
    {
        // Assigning a staff user (or owner) as the courier must fail
        // even if the row is otherwise valid. Role check is enforced
        // in the domain layer, not just in the form validator, so
        // this protects against direct DB updates or seeded data.
        $owner = User::factory()->owner()->create();
        $notACourier = User::factory()->staff()->create();

        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->create([
                'created_by_user_id' => $owner->id,
                'courier_id' => $notACourier->id,
                'scheduled_at' => now()->addHours(2),
            ]);

        $this->actingAs($owner);
        $this->post("/deliveries/{$delivery->id}/schedule")
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $this->assertSame(DeliveryStatus::Draft, $delivery->fresh()->status);
    }

    public function test_schedule_rejects_draft_with_inactive_courier(): void
    {
        // AR-37: the courier must be *active* at the moment of
        // scheduling. Deactivating a courier is a legitimate
        // operational action (staff turnover); pending drafts that
        // still point at them must fall over cleanly instead of
        // silently succeeding.
        $owner = User::factory()->owner()->create();
        $courier = User::factory()->courier()->inactive()->create();
        $delivery = $this->draft($owner, $courier);

        $this->actingAs($owner);
        $this->post("/deliveries/{$delivery->id}/schedule")
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $this->assertSame(DeliveryStatus::Draft, $delivery->fresh()->status);
    }

    public function test_schedule_rejects_draft_when_courier_at_capacity(): void
    {
        // AR-34: default per-courier concurrency limit is 1. Each
        // courier may have at most one active (non-terminal)
        // delivery at a time; assigning a second draft to the same
        // courier and trying to schedule it must be rejected.
        config()->set('delivery.max_concurrent_per_courier', 1);

        $owner = User::factory()->owner()->create();
        $courier = User::factory()->courier()->create();

        // First delivery is already scheduled against this courier.
        Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create([
                'created_by_user_id' => $owner->id,
                'courier_id' => $courier->id,
            ]);

        // Second draft, same courier, should be rejected on schedule.
        $second = $this->draft($owner, $courier);

        $this->actingAs($owner);
        $this->post("/deliveries/{$second->id}/schedule")
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $this->assertSame(DeliveryStatus::Draft, $second->fresh()->status);
    }

    public function test_configurable_per_courier_cap_permits_multiple(): void
    {
        // AR-34: the per-courier cap is configurable via
        // delivery.max_concurrent_per_courier. Raising it lets a
        // single courier fulfil multiple parallel deliveries — an
        // operational lever the business can use during peak hours.
        config()->set('delivery.max_concurrent_per_courier', 3);
        config()->set('delivery.max_concurrent_active', 10);

        $owner = User::factory()->owner()->create();
        $courier = User::factory()->courier()->create();

        Delivery::factory()
            ->count(2)
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create([
                'created_by_user_id' => $owner->id,
                'courier_id' => $courier->id,
            ]);

        $third = $this->draft($owner, $courier);

        $this->actingAs($owner);
        $this->post("/deliveries/{$third->id}/schedule")->assertRedirect();
        $this->assertSame(DeliveryStatus::Scheduled, $third->fresh()->status);
    }

    public function test_deactivating_courier_between_draft_and_schedule_fails_safely(): void
    {
        // Regression guard: the form validator ensures the courier is
        // active at write time, but nothing prevents the courier from
        // being deactivated in the interval before scheduling. The
        // domain-level check catches this case.
        $owner = User::factory()->owner()->create();
        $courier = User::factory()->courier()->create();
        $delivery = $this->draft($owner, $courier);

        // Simulate the courier being deactivated after the draft was
        // saved (e.g. staff off-boarding) but before scheduling runs.
        $courier->forceFill(['is_active' => false])->save();

        $this->actingAs($owner);
        $this->post("/deliveries/{$delivery->id}/schedule")
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $this->assertSame(DeliveryStatus::Draft, $delivery->fresh()->status);
    }
}
