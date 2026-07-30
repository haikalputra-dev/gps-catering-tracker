<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Kitchen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DeliveryRouteTest extends TestCase
{
    use RefreshDatabase;

    private function draft(User $owner): Delivery
    {
        return Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->create(['created_by_user_id' => $owner->id]);
    }

    public function test_only_ten_delivery_routes_are_registered(): void
    {
        // Packet 09 added `dispatch` and `mark-delivered` to the delivery
        // lifecycle. The set of route names is closed to prevent
        // accidental drift (AR-41): no telemetry, no GPS, no customer
        // surfaces, no real-time endpoints.
        $names = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($r) => $r->getName())
            ->filter(fn ($n) => is_string($n) && str_starts_with($n, 'deliveries.'))
            ->values()
            ->all();

        sort($names);

        $this->assertSame(
            [
                'deliveries.cancel',
                'deliveries.create',
                'deliveries.dispatch',
                'deliveries.edit',
                'deliveries.index',
                'deliveries.mark-delivered',
                'deliveries.schedule',
                'deliveries.show',
                'deliveries.store',
                'deliveries.update',
            ],
            $names,
        );
    }

    public function test_no_track_route_exists(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = $this->draft($owner);

        $this->actingAs($owner);
        $this->get("/deliveries/{$delivery->id}/track")->assertNotFound();
    }

    public function test_no_alternative_dispatch_or_complete_routes_exist(): void
    {
        // Packet 09 canonicalises `dispatch` and `mark-delivered` as the
        // only names for those transitions. Legacy or ad-hoc aliases
        // (`complete`, `deliver`, `finish`, `arrive`, `arrived`) must
        // 404 so route drift cannot bypass the state-machine guards.
        $owner = User::factory()->owner()->create();
        $delivery = $this->draft($owner);

        $this->actingAs($owner);
        $this->post("/deliveries/{$delivery->id}/complete")->assertNotFound();
        $this->post("/deliveries/{$delivery->id}/deliver")->assertNotFound();
        $this->post("/deliveries/{$delivery->id}/finish")->assertNotFound();
        $this->post("/deliveries/{$delivery->id}/arrive")->assertNotFound();
        $this->post("/deliveries/{$delivery->id}/arrived")->assertNotFound();
    }

    public function test_no_api_route_exists(): void
    {
        $this->get('/api/deliveries')->assertNotFound();
    }

    public function test_public_receipt_lookup_route_does_not_exist(): void
    {
        $this->get('/track')->assertNotFound();
        $this->get('/receipts/lookup')->assertNotFound();
    }
}
