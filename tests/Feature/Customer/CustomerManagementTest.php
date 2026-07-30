<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Alpha Buyer',
            'phone' => '+62 812-3456-7890',
            'address' => 'Jl. Contoh No. 1',
            'latitude' => '-6.9175000',
            'longitude' => '106.9270000',
            'notes' => 'Ring the front bell.',
            'is_active' => '1',
        ], $overrides);
    }

    public function test_owner_can_create_customer_with_normalized_phone(): void
    {
        $this->actingAs(User::factory()->owner()->create());

        $response = $this->post('/customers', $this->validPayload());
        $response->assertRedirect(route('customers.index'));
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('customers', [
            'name' => 'Alpha Buyer',
            'phone' => '+6281234567890',
            'address' => 'Jl. Contoh No. 1',
            'is_active' => true,
        ]);
    }

    public function test_staff_can_create_customer(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        $this->post('/customers', $this->validPayload([
            'phone' => '081234567891',
        ]))->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', ['phone' => '081234567891']);
    }

    public function test_owner_can_update_customer(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->owner()->create());

        $this->put("/customers/{$customer->id}", $this->validPayload([
            'name' => 'Updated Buyer',
            'phone' => '+62 813-9999-0000',
        ]))->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'Updated Buyer',
            'phone' => '+6281399990000',
        ]);
    }

    public function test_customer_can_be_marked_inactive_via_update(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->owner()->create());

        $this->put("/customers/{$customer->id}", $this->validPayload([
            'phone' => $customer->phone,
            'is_active' => '0',
        ]))->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'is_active' => false,
        ]);
    }

    public function test_customer_can_be_reactivated_via_update(): void
    {
        $customer = Customer::factory()->inactive()->create();
        $this->actingAs(User::factory()->owner()->create());

        $this->put("/customers/{$customer->id}", $this->validPayload([
            'phone' => $customer->phone,
            'is_active' => '1',
        ]))->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'is_active' => true,
        ]);
    }

    public function test_index_orders_active_before_inactive(): void
    {
        $inactive = Customer::factory()->inactive()->create(['name' => 'AAA Inactive']);
        $active = Customer::factory()->create(['name' => 'ZZZ Active']);

        $this->actingAs(User::factory()->owner()->create());

        $response = $this->get('/customers');
        $response->assertOk();

        $body = $response->getContent();
        $this->assertNotFalse($body);
        $posActive = strpos($body, 'ZZZ Active');
        $posInactive = strpos($body, 'AAA Inactive');
        $this->assertNotFalse($posActive);
        $this->assertNotFalse($posInactive);
        $this->assertLessThan($posInactive, $posActive);
    }

    public function test_edit_page_renders_for_existing_customer(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->owner()->create());

        $this->get("/customers/{$customer->id}/edit")
            ->assertOk()
            ->assertSee($customer->name);
    }

    public function test_delete_route_does_not_exist(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->owner()->create());

        // No DELETE route is registered for customers.
        $this->delete("/customers/{$customer->id}")->assertMethodNotAllowed();
        $this->assertDatabaseHas('customers', ['id' => $customer->id]);
    }

    public function test_index_masks_phone_in_listing(): void
    {
        $customer = Customer::factory()->create(['phone' => '+6281234567890']);
        $this->actingAs(User::factory()->owner()->create());

        $response = $this->get('/customers');
        $response->assertOk();
        $response->assertDontSee('+6281234567890');
        // The last four digits should still be visible for recognition.
        $response->assertSee('7890');
    }
}
