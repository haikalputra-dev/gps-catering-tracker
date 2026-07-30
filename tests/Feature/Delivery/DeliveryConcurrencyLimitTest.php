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

class DeliveryConcurrencyLimitTest extends TestCase
{
    use RefreshDatabase;

    private function draft(User $owner): Delivery
    {
        // AR-37: a scheduled delivery must have an active courier. These
        // tests exercise the *cap* rather than the courier check, so we
        // pre-assign a fresh courier per draft.
        return Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->create([
                'created_by_user_id' => $owner->id,
                'courier_id' => User::factory()->courier()->create()->id,
                'scheduled_at' => now()->addHours(3),
            ]);
    }

    public function test_scheduling_is_blocked_when_default_limit_reached(): void
    {
        config()->set('delivery.max_concurrent_active', 1);

        $owner = User::factory()->owner()->create();

        Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create(['created_by_user_id' => $owner->id]);

        $draft = $this->draft($owner);

        $this->actingAs($owner);
        $this->post("/deliveries/{$draft->id}/schedule")
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $draft->refresh();
        $this->assertSame(DeliveryStatus::Draft, $draft->status);
        $this->assertNull($draft->receipt_number);
    }

    public function test_scheduling_succeeds_after_terminal_delivery_frees_slot(): void
    {
        config()->set('delivery.max_concurrent_active', 1);

        $owner = User::factory()->owner()->create();

        // Cancelled deliveries are terminal and do NOT count.
        Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->cancelledFromScheduled()
            ->create(['created_by_user_id' => $owner->id]);

        $draft = $this->draft($owner);

        $this->actingAs($owner);
        $this->post("/deliveries/{$draft->id}/schedule")
            ->assertRedirect();

        $draft->refresh();
        $this->assertSame(DeliveryStatus::Scheduled, $draft->status);
    }

    public function test_configurable_limit_permits_multiple_active_deliveries(): void
    {
        config()->set('delivery.max_concurrent_active', 3);

        $owner = User::factory()->owner()->create();

        // Two already-scheduled deliveries count against the cap.
        Delivery::factory()
            ->count(2)
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create(['created_by_user_id' => $owner->id]);

        $draft = $this->draft($owner);

        $this->actingAs($owner);
        $this->post("/deliveries/{$draft->id}/schedule")
            ->assertRedirect();

        $draft->refresh();
        $this->assertSame(DeliveryStatus::Scheduled, $draft->status);
    }

    public function test_zero_limit_blocks_all_scheduling(): void
    {
        config()->set('delivery.max_concurrent_active', 0);

        $owner = User::factory()->owner()->create();
        $draft = $this->draft($owner);

        $this->actingAs($owner);
        $this->post("/deliveries/{$draft->id}/schedule")
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $draft->refresh();
        $this->assertSame(DeliveryStatus::Draft, $draft->status);
    }

    public function test_drafts_count_toward_concurrency_limit(): void
    {
        // Two drafts consume the cap; scheduling the third draft is rejected
        // because "active" (non-terminal) statuses include `draft`.
        config()->set('delivery.max_concurrent_active', 2);

        $owner = User::factory()->owner()->create();

        // Two existing drafts.
        $this->draft($owner);
        $this->draft($owner);

        $target = $this->draft($owner);

        $this->actingAs($owner);
        $this->post("/deliveries/{$target->id}/schedule")
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $target->refresh();
        $this->assertSame(DeliveryStatus::Draft, $target->status);
    }
}
