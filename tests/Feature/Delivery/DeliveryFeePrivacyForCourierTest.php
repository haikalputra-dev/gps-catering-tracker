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
 * Packet 09 — Fee privacy for couriers (AR-40).
 *
 * The courier role is a purely operational actor: they see WHERE to
 * pick up, WHERE to drop off, and when to mark the delivery as
 * dispatched or delivered. They do NOT see the money side (the
 * per-delivery fee, distance-based pricing breakdown, or the
 * aggregate index fee column).
 *
 * These tests lock the DOM-absence invariant so a regression in the
 * Blade templates cannot silently leak pricing to couriers.
 */
class DeliveryFeePrivacyForCourierTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Build a scheduled delivery assigned to $courier.
     *
     * We use the factory `scheduled()` state so `fee_rupiah` and
     * `distance_km` are already frozen — that's what we're checking
     * the view does (or does not) render.
     */
    private function scheduledFor(User $courier): Delivery
    {
        return Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create(['courier_id' => $courier->id]);
    }

    public function test_courier_show_page_hides_fee_and_pricing_section(): void
    {
        // The assigned courier CAN view the show page (AR-41) but the
        // Pricing card, distance readout, and fee value must not
        // appear in the response body.
        $courier = User::factory()->courier()->create();
        $delivery = $this->scheduledFor($courier);

        $formattedFee = 'Rp '.number_format((int) $delivery->fee_rupiah, 0, ',', '.');

        $this->actingAs($courier);
        $response = $this->get("/deliveries/{$delivery->id}");

        $response->assertOk();
        $response->assertDontSee('Pricing');
        $response->assertDontSee('Distance:');
        $response->assertDontSee($formattedFee);
        // Sanity: the row IS the courier's and the receipt still
        // renders so we know the page loaded fully rather than
        // silently redirecting.
        $response->assertSee((string) $delivery->receipt_number);
    }

    public function test_owner_show_page_still_renders_fee_and_pricing_section(): void
    {
        // Contrast case: the owner MUST see the fee. If this fails,
        // the fee-privacy branch has accidentally caught office users
        // as well, which would break the delivery-management UX.
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

        $formattedFee = 'Rp '.number_format((int) $delivery->fee_rupiah, 0, ',', '.');

        $this->actingAs($owner);
        $this->get("/deliveries/{$delivery->id}")
            ->assertOk()
            ->assertSee('Pricing')
            ->assertSee('Distance:')
            ->assertSee($formattedFee);
    }

    public function test_staff_show_page_still_renders_fee_and_pricing_section(): void
    {
        // Staff also has full financial visibility.
        $staff = User::factory()->staff()->create();
        $courier = User::factory()->courier()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create([
                'created_by_user_id' => $staff->id,
                'courier_id' => $courier->id,
            ]);

        $formattedFee = 'Rp '.number_format((int) $delivery->fee_rupiah, 0, ',', '.');

        $this->actingAs($staff);
        $this->get("/deliveries/{$delivery->id}")
            ->assertOk()
            ->assertSee('Pricing')
            ->assertSee($formattedFee);
    }

    public function test_courier_dashboard_hides_fee(): void
    {
        // AR-40: the courier's daily dashboard shows their active
        // delivery(ies) so they know where to go, but never displays
        // a fee value. This is the primary courier-facing surface
        // and the most likely regression target.
        $courier = User::factory()->courier()->create();
        $delivery = $this->scheduledFor($courier);

        $formattedFee = 'Rp '.number_format((int) $delivery->fee_rupiah, 0, ',', '.');

        $this->actingAs($courier);
        $response = $this->get(route('courier.dashboard'));

        $response->assertOk();
        $response->assertDontSee($formattedFee);
        $response->assertDontSee('Fee');
    }
}
