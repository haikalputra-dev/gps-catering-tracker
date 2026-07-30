<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerValidationTest extends TestCase
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
            'notes' => null,
            'is_active' => '1',
        ], $overrides);
    }

    public function test_store_requires_name(): void
    {
        $this->actingAs(User::factory()->owner()->create());
        $this->from('/customers/create')
            ->post('/customers', $this->validPayload(['name' => '']))
            ->assertRedirect('/customers/create')
            ->assertSessionHasErrors('name');
    }

    public function test_store_requires_phone(): void
    {
        $this->actingAs(User::factory()->owner()->create());
        $this->from('/customers/create')
            ->post('/customers', $this->validPayload(['phone' => '']))
            ->assertRedirect('/customers/create')
            ->assertSessionHasErrors('phone');
    }

    public function test_store_rejects_phone_with_letters(): void
    {
        $this->actingAs(User::factory()->owner()->create());
        $this->from('/customers/create')
            ->post('/customers', $this->validPayload(['phone' => '+6281ABC5678']))
            ->assertRedirect('/customers/create')
            ->assertSessionHasErrors('phone');
    }

    public function test_store_rejects_phone_too_short(): void
    {
        $this->actingAs(User::factory()->owner()->create());
        $this->from('/customers/create')
            ->post('/customers', $this->validPayload(['phone' => '+62812']))
            ->assertRedirect('/customers/create')
            ->assertSessionHasErrors('phone');
    }

    public function test_store_rejects_phone_too_long(): void
    {
        $this->actingAs(User::factory()->owner()->create());
        $this->from('/customers/create')
            ->post('/customers', $this->validPayload(['phone' => '+1234567890123456']))
            ->assertRedirect('/customers/create')
            ->assertSessionHasErrors('phone');
    }

    public function test_store_rejects_duplicate_phone_after_normalization(): void
    {
        Customer::factory()->create(['phone' => '+6281234567890']);
        $this->actingAs(User::factory()->owner()->create());

        $this->from('/customers/create')
            ->post('/customers', $this->validPayload([
                'phone' => '+62 812-3456-7890',
            ]))
            ->assertRedirect('/customers/create')
            ->assertSessionHasErrors('phone');
    }

    public function test_update_allows_keeping_same_phone(): void
    {
        $customer = Customer::factory()->create(['phone' => '+6281234567890']);
        $this->actingAs(User::factory()->owner()->create());

        $this->put("/customers/{$customer->id}", $this->validPayload([
            'phone' => '+62 812-3456-7890',
        ]))->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'phone' => '+6281234567890',
        ]);
    }

    public function test_store_requires_address(): void
    {
        $this->actingAs(User::factory()->owner()->create());
        $this->from('/customers/create')
            ->post('/customers', $this->validPayload(['address' => '']))
            ->assertRedirect('/customers/create')
            ->assertSessionHasErrors('address');
    }

    public function test_store_rejects_latitude_out_of_range(): void
    {
        $this->actingAs(User::factory()->owner()->create());
        $this->from('/customers/create')
            ->post('/customers', $this->validPayload(['latitude' => '95.0']))
            ->assertRedirect('/customers/create')
            ->assertSessionHasErrors('latitude');
    }

    public function test_store_rejects_longitude_out_of_range(): void
    {
        $this->actingAs(User::factory()->owner()->create());
        $this->from('/customers/create')
            ->post('/customers', $this->validPayload(['longitude' => '200.0']))
            ->assertRedirect('/customers/create')
            ->assertSessionHasErrors('longitude');
    }

    public function test_store_requires_coordinates(): void
    {
        $this->actingAs(User::factory()->owner()->create());
        $this->from('/customers/create')
            ->post('/customers', $this->validPayload([
                'latitude' => '',
                'longitude' => '',
            ]))
            ->assertRedirect('/customers/create')
            ->assertSessionHasErrors(['latitude', 'longitude']);
    }

    public function test_store_accepts_null_notes(): void
    {
        $this->actingAs(User::factory()->owner()->create());
        $this->post('/customers', $this->validPayload([
            'notes' => '',
        ]))->assertRedirect(route('customers.index'));

        $this->assertDatabaseHas('customers', [
            'phone' => '+6281234567890',
            'notes' => null,
        ]);
    }
}
