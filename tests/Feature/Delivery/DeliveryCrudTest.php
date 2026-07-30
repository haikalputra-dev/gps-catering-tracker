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

class DeliveryCrudTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function payload(int $kitchenId, int $customerId, array $overrides = []): array
    {
        return array_merge([
            'kitchen_id' => $kitchenId,
            'customer_id' => $customerId,
            'scheduled_at' => now()->addHours(4)->format('Y-m-d\TH:i'),
            'notes' => 'Deliver to reception desk.',
        ], $overrides);
    }

    public function test_owner_can_create_draft(): void
    {
        $owner = User::factory()->owner()->create();
        $kitchen = Kitchen::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($owner);
        $response = $this->post('/deliveries', $this->payload($kitchen->id, $customer->id));

        $this->assertDatabaseCount('deliveries', 1);
        $delivery = Delivery::query()->firstOrFail();
        $response->assertRedirect(route('deliveries.show', $delivery));

        $this->assertSame(DeliveryStatus::Draft, $delivery->status);
        $this->assertNull($delivery->receipt_number);
        $this->assertNull($delivery->kitchen_code);
        $this->assertNull($delivery->customer_name);
        $this->assertSame($owner->id, $delivery->created_by_user_id);
    }

    public function test_staff_can_create_draft(): void
    {
        $staff = User::factory()->staff()->create();
        $kitchen = Kitchen::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($staff);
        $this->post('/deliveries', $this->payload($kitchen->id, $customer->id))
            ->assertRedirect();

        $this->assertDatabaseHas('deliveries', [
            'status' => 'draft',
            'created_by_user_id' => $staff->id,
        ]);
    }

    public function test_draft_can_be_created_without_scheduled_at(): void
    {
        $owner = User::factory()->owner()->create();
        $kitchen = Kitchen::factory()->create();
        $customer = Customer::factory()->create();

        $this->actingAs($owner);
        $this->post('/deliveries', $this->payload($kitchen->id, $customer->id, [
            'scheduled_at' => '',
        ]))->assertRedirect();

        $this->assertDatabaseHas('deliveries', [
            'created_by_user_id' => $owner->id,
            'scheduled_at' => null,
        ]);
    }

    public function test_draft_can_be_updated(): void
    {
        $owner = User::factory()->owner()->create();
        $kitchenA = Kitchen::factory()->create();
        $kitchenB = Kitchen::factory()->create();
        $customer = Customer::factory()->create();

        $delivery = Delivery::factory()->create([
            'kitchen_id' => $kitchenA->id,
            'customer_id' => $customer->id,
            'created_by_user_id' => $owner->id,
            'notes' => 'Old notes',
        ]);

        $this->actingAs($owner);
        $this->put("/deliveries/{$delivery->id}", $this->payload($kitchenB->id, $customer->id, [
            'notes' => 'New notes',
        ]))->assertRedirect(route('deliveries.show', $delivery));

        $this->assertDatabaseHas('deliveries', [
            'id' => $delivery->id,
            'kitchen_id' => $kitchenB->id,
            'notes' => 'New notes',
        ]);
    }

    public function test_show_page_renders_for_draft(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->create(['created_by_user_id' => $owner->id]);

        $this->actingAs($owner);
        $this->get("/deliveries/{$delivery->id}")
            ->assertOk()
            ->assertSee('Delivery #'.$delivery->id);
    }

    public function test_index_lists_delivery(): void
    {
        $owner = User::factory()->owner()->create();
        Delivery::factory()
            ->for(Kitchen::factory(['code' => 'K-INDEX']), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->create(['created_by_user_id' => $owner->id]);

        $this->actingAs($owner);
        $this->get('/deliveries')
            ->assertOk()
            ->assertSee('K-INDEX');
    }

    public function test_delete_route_does_not_exist(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->create(['created_by_user_id' => $owner->id]);

        $this->actingAs($owner);
        $this->delete("/deliveries/{$delivery->id}")->assertMethodNotAllowed();
        $this->assertDatabaseHas('deliveries', ['id' => $delivery->id]);
    }
}
