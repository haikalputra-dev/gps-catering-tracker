<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Kitchen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryValidationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(int $kitchenId, int $customerId, array $overrides = []): array
    {
        return array_merge([
            'kitchen_id' => $kitchenId,
            'customer_id' => $customerId,
            'scheduled_at' => now()->addHours(2)->format('Y-m-d\TH:i'),
            'notes' => 'Basic notes',
        ], $overrides);
    }

    public function test_store_requires_kitchen_id(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($owner);
        $this->post('/deliveries', $this->payload(0, $customer->id, [
            'kitchen_id' => '',
        ]))->assertSessionHasErrors('kitchen_id');

        $this->assertDatabaseCount('deliveries', 0);
    }

    public function test_store_requires_customer_id(): void
    {
        $owner = User::factory()->owner()->create();
        $kitchen = Kitchen::factory()->create();

        $this->actingAs($owner);
        $this->post('/deliveries', $this->payload($kitchen->id, 0, [
            'customer_id' => '',
        ]))->assertSessionHasErrors('customer_id');
    }

    public function test_store_rejects_inactive_kitchen(): void
    {
        $owner = User::factory()->owner()->create();
        $kitchen = Kitchen::factory()->create(['is_active' => false]);
        $customer = Customer::factory()->create();

        $this->actingAs($owner);
        $this->post('/deliveries', $this->payload($kitchen->id, $customer->id))
            ->assertSessionHasErrors('kitchen_id');
    }

    public function test_store_rejects_inactive_customer(): void
    {
        $owner = User::factory()->owner()->create();
        $kitchen = Kitchen::factory()->create();
        $customer = Customer::factory()->inactive()->create();

        $this->actingAs($owner);
        $this->post('/deliveries', $this->payload($kitchen->id, $customer->id))
            ->assertSessionHasErrors('customer_id');
    }

    public function test_store_rejects_past_scheduled_at(): void
    {
        $owner = User::factory()->owner()->create();
        $kitchen = Kitchen::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($owner);
        $this->post('/deliveries', $this->payload($kitchen->id, $customer->id, [
            'scheduled_at' => now()->subHour()->format('Y-m-d\TH:i'),
        ]))->assertSessionHasErrors('scheduled_at');
    }

    public function test_store_rejects_notes_over_1000_chars(): void
    {
        $owner = User::factory()->owner()->create();
        $kitchen = Kitchen::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($owner);
        $this->post('/deliveries', $this->payload($kitchen->id, $customer->id, [
            'notes' => str_repeat('x', 1001),
        ]))->assertSessionHasErrors('notes');
    }

    public function test_update_only_allowed_when_status_is_draft(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create(['created_by_user_id' => $owner->id]);

        $this->actingAs($owner);
        $this->put("/deliveries/{$delivery->id}", $this->payload(
            $delivery->kitchen_id,
            $delivery->customer_id,
        ))->assertRedirect();

        $delivery->refresh();
        $this->assertSame('scheduled', $delivery->status->value);
    }

    public function test_cancel_requires_reason(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->create(['created_by_user_id' => $owner->id]);

        $this->actingAs($owner);
        $this->post("/deliveries/{$delivery->id}/cancel", [])
            ->assertSessionHasErrors('cancellation_reason');
    }

    public function test_cancel_reason_min_length(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->create(['created_by_user_id' => $owner->id]);

        $this->actingAs($owner);
        $this->post("/deliveries/{$delivery->id}/cancel", [
            'cancellation_reason' => 'no',
        ])->assertSessionHasErrors('cancellation_reason');
    }

    public function test_cancel_reason_max_length(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->create(['created_by_user_id' => $owner->id]);

        $this->actingAs($owner);
        $this->post("/deliveries/{$delivery->id}/cancel", [
            'cancellation_reason' => str_repeat('a', 256),
        ])->assertSessionHasErrors('cancellation_reason');
    }
}
