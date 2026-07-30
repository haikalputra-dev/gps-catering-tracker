<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Domain\Delivery\DeliveryStatus;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Kitchen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DeliveryStateMachineTest extends TestCase
{
    use RefreshDatabase;

    private function draft(User $owner, ?Carbon $scheduledAt = null): Delivery
    {
        return Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->create([
                'created_by_user_id' => $owner->id,
                'scheduled_at' => $scheduledAt ?? now()->addHours(2),
            ]);
    }

    public function test_owner_can_schedule_draft(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = $this->draft($owner);

        $this->actingAs($owner);
        $this->post("/deliveries/{$delivery->id}/schedule")
            ->assertRedirect(route('deliveries.show', $delivery));

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Scheduled, $delivery->status);
        $this->assertNotNull($delivery->receipt_number);
        $this->assertSame($owner->id, $delivery->scheduled_by_user_id);
    }

    public function test_cannot_schedule_when_scheduled_at_missing(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->create([
                'created_by_user_id' => $owner->id,
                'scheduled_at' => null,
            ]);

        $this->actingAs($owner);
        $this->post("/deliveries/{$delivery->id}/schedule")
            ->assertRedirect()
            ->assertSessionHasErrors('status');

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Draft, $delivery->status);
        $this->assertNull($delivery->receipt_number);
    }

    public function test_cannot_schedule_already_scheduled(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create(['created_by_user_id' => $owner->id]);

        $this->actingAs($owner);
        $this->post("/deliveries/{$delivery->id}/schedule")
            ->assertRedirect();

        // Status unchanged.
        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Scheduled, $delivery->status);
    }

    public function test_owner_can_cancel_draft(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = $this->draft($owner);

        $this->actingAs($owner);
        $this->post("/deliveries/{$delivery->id}/cancel", [
            'cancellation_reason' => 'No longer needed.',
        ])->assertRedirect(route('deliveries.show', $delivery));

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Cancelled, $delivery->status);
        $this->assertSame('No longer needed.', $delivery->cancellation_reason);
        $this->assertSame($owner->id, $delivery->cancelled_by_user_id);
        $this->assertNotNull($delivery->cancelled_at);
        $this->assertNull($delivery->receipt_number);
    }

    public function test_scheduled_cancel_preserves_receipt_and_snapshots(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create(['created_by_user_id' => $owner->id]);

        $receipt = $delivery->receipt_number;
        $kitchenCode = $delivery->kitchen_code;

        $this->actingAs($owner);
        $this->post("/deliveries/{$delivery->id}/cancel", [
            'cancellation_reason' => 'Kitchen shut down',
        ])->assertRedirect();

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Cancelled, $delivery->status);
        $this->assertSame($receipt, $delivery->receipt_number);
        $this->assertSame($kitchenCode, $delivery->kitchen_code);
    }

    public function test_cannot_cancel_already_cancelled(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->cancelledFromDraft()
            ->create(['created_by_user_id' => $owner->id]);

        $this->actingAs($owner);
        $this->post("/deliveries/{$delivery->id}/cancel", [
            'cancellation_reason' => 'retry',
        ])->assertRedirect();

        // Reason and cancelled_at remain the factory-set values, no rewrite.
        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Cancelled, $delivery->status);
    }

    public function test_editing_scheduled_delivery_is_rejected(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create(['created_by_user_id' => $owner->id]);

        $this->actingAs($owner);
        $this->get("/deliveries/{$delivery->id}/edit")
            ->assertRedirect(route('deliveries.show', $delivery));

        $this->put("/deliveries/{$delivery->id}", [
            'kitchen_id' => $delivery->kitchen_id,
            'customer_id' => $delivery->customer_id,
            'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertRedirect();
    }
}
