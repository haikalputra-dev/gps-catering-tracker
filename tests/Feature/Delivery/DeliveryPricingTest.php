<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Domain\Delivery\DeliveryStatus;
use App\Domain\Delivery\DistanceCalculator;
use App\Domain\Delivery\PricingCalculator;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Kitchen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for AR-29..AR-33 covering the pricing surface:
 * distance/fee are frozen on draft->scheduled and preserved on cancel.
 */
class DeliveryPricingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Pin defaults so no local .env drift affects these tests.
        config()->set('pricing.minimum_fee_rupiah', 5000);
        config()->set('pricing.rate_per_km_rupiah', 2000);
        config()->set('pricing.fee_rounding_step_rupiah', 100);
    }

    private function draftAt(User $owner, Kitchen $kitchen, Customer $customer): Delivery
    {
        // AR-37: scheduling requires an assigned active courier. These
        // pricing tests focus on the distance/fee freeze at scheduling,
        // so we seed a fresh courier on every draft to keep the courier
        // precondition satisfied.
        return Delivery::factory()
            ->for($kitchen, 'kitchen')
            ->for($customer, 'customer')
            ->create([
                'created_by_user_id' => $owner->id,
                'courier_id' => User::factory()->courier()->create()->id,
                'scheduled_at' => now()->addHours(2),
            ]);
    }

    public function test_draft_has_null_distance_and_fee(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = $this->draftAt(
            $owner,
            Kitchen::factory()->create(),
            Customer::factory()->create(),
        );

        $this->assertNull($delivery->distance_km);
        $this->assertNull($delivery->fee_rupiah);
    }

    public function test_scheduling_freezes_distance_and_fee_from_snapshot(): void
    {
        $owner = User::factory()->owner()->create();
        $kitchen = Kitchen::factory()->create([
            'latitude' => -6.1754,
            'longitude' => 106.8272,
        ]);
        $customer = Customer::factory()->create([
            'latitude' => -6.5950,
            'longitude' => 106.7962,
        ]);
        $delivery = $this->draftAt($owner, $kitchen, $customer);

        $this->actingAs($owner);
        $this->post("/deliveries/{$delivery->id}/schedule")
            ->assertRedirect(route('deliveries.show', $delivery));

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Scheduled, $delivery->status);

        $expectedKm = round(
            app(DistanceCalculator::class)->between(-6.1754, 106.8272, -6.5950, 106.7962),
            3,
            PHP_ROUND_HALF_UP,
        );
        $expectedFee = app(PricingCalculator::class)->feeForDistanceKm($expectedKm);

        $this->assertSame(
            number_format($expectedKm, 3, '.', ''),
            $delivery->distance_km,
            'distance_km should be the frozen Haversine value cast to decimal(8,3).',
        );
        $this->assertSame($expectedFee, $delivery->fee_rupiah);
    }

    public function test_distance_and_fee_are_preserved_on_cancel(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create(['created_by_user_id' => $owner->id]);

        $originalDistance = $delivery->distance_km;
        $originalFee = $delivery->fee_rupiah;

        $this->actingAs($owner);
        $this->post("/deliveries/{$delivery->id}/cancel", [
            'cancellation_reason' => 'Customer rescheduled.',
        ])->assertRedirect();

        $delivery->refresh();
        $this->assertSame(DeliveryStatus::Cancelled, $delivery->status);
        $this->assertSame($originalDistance, $delivery->distance_km);
        $this->assertSame($originalFee, $delivery->fee_rupiah);
    }

    public function test_distance_and_fee_do_not_change_when_source_moves(): void
    {
        $owner = User::factory()->owner()->create();
        $kitchen = Kitchen::factory()->create([
            'latitude' => -6.1754,
            'longitude' => 106.8272,
        ]);
        $customer = Customer::factory()->create([
            'latitude' => -6.2000,
            'longitude' => 106.8300,
        ]);
        $delivery = $this->draftAt($owner, $kitchen, $customer);

        $this->actingAs($owner);
        $this->post("/deliveries/{$delivery->id}/schedule")->assertRedirect();
        $delivery->refresh();

        $frozenDistance = $delivery->distance_km;
        $frozenFee = $delivery->fee_rupiah;

        // Move both endpoints after scheduling.
        $kitchen->update(['latitude' => 0.0, 'longitude' => 0.0]);
        $customer->update(['latitude' => 40.0, 'longitude' => 40.0]);

        $delivery->refresh();
        $this->assertSame($frozenDistance, $delivery->distance_km);
        $this->assertSame($frozenFee, $delivery->fee_rupiah);
    }

    public function test_show_page_renders_pricing_for_scheduled_delivery(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create(['created_by_user_id' => $owner->id]);

        $formattedFee = 'Rp '.number_format((int) $delivery->fee_rupiah, 0, ',', '.');

        $this->actingAs($owner);
        $this->get("/deliveries/{$delivery->id}")
            ->assertOk()
            ->assertSee('Pricing')
            ->assertSee('Distance:')
            ->assertSee('Fee:')
            ->assertSee($formattedFee);
    }

    public function test_show_page_renders_placeholder_for_draft(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->create(['created_by_user_id' => $owner->id]);

        $this->actingAs($owner);
        $this->get("/deliveries/{$delivery->id}")
            ->assertOk()
            ->assertSee('Pricing')
            ->assertSee('Distance:');
    }

    public function test_index_renders_fee_column(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create(['created_by_user_id' => $owner->id]);

        $formattedFee = 'Rp '.number_format((int) $delivery->fee_rupiah, 0, ',', '.');

        $this->actingAs($owner);
        $this->get('/deliveries')
            ->assertOk()
            ->assertSee('Fee')
            ->assertSee($formattedFee);
    }

    public function test_config_overrides_are_honored_at_scheduling(): void
    {
        config()->set('pricing.minimum_fee_rupiah', 10000);
        config()->set('pricing.rate_per_km_rupiah', 3000);
        config()->set('pricing.fee_rounding_step_rupiah', 500);

        $owner = User::factory()->owner()->create();
        $kitchen = Kitchen::factory()->create([
            'latitude' => -6.1754,
            'longitude' => 106.8272,
        ]);
        $customer = Customer::factory()->create([
            'latitude' => -6.5950,
            'longitude' => 106.7962,
        ]);
        $delivery = $this->draftAt($owner, $kitchen, $customer);

        $this->actingAs($owner);
        $this->post("/deliveries/{$delivery->id}/schedule")->assertRedirect();
        $delivery->refresh();

        $expectedKm = round(
            app(DistanceCalculator::class)->between(-6.1754, 106.8272, -6.5950, 106.7962),
            3,
            PHP_ROUND_HALF_UP,
        );
        $expectedFee = app(PricingCalculator::class)->feeForDistanceKm($expectedKm);

        $this->assertSame($expectedFee, $delivery->fee_rupiah);
        $this->assertGreaterThanOrEqual(10000, $delivery->fee_rupiah);
    }
}
