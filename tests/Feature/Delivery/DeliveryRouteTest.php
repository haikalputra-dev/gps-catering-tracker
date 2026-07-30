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

    public function test_only_eight_delivery_routes_are_registered(): void
    {
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
                'deliveries.edit',
                'deliveries.index',
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

    public function test_no_dispatch_or_complete_route_exists(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = $this->draft($owner);

        $this->actingAs($owner);
        $this->post("/deliveries/{$delivery->id}/dispatch")->assertNotFound();
        $this->post("/deliveries/{$delivery->id}/complete")->assertNotFound();
        $this->post("/deliveries/{$delivery->id}/deliver")->assertNotFound();
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
