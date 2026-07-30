<?php

declare(strict_types=1);

namespace Tests\Feature\Kitchen;

use App\Models\Kitchen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_inactive_kitchens_remain_visible_in_index(): void
    {
        Kitchen::factory()->create(['code' => 'ACTIVE-1', 'name' => 'Alpha', 'is_active' => true]);
        Kitchen::factory()->inactive()->create(['code' => 'INACT-1', 'name' => 'Beta', 'is_active' => false]);

        $this->actingAs(User::factory()->owner()->create());
        $response = $this->get('/kitchens');

        $response->assertOk();
        $response->assertSee('ACTIVE-1');
        $response->assertSee('INACT-1');
    }

    public function test_index_orders_active_before_inactive(): void
    {
        Kitchen::factory()->inactive()->create(['code' => 'INACT-A', 'name' => 'Aaa']);
        Kitchen::factory()->create(['code' => 'ACT-Z', 'name' => 'Zzz']);

        $this->actingAs(User::factory()->owner()->create());
        $body = $this->get('/kitchens')->getContent();

        $posActive = strpos($body, 'ACT-Z');
        $posInactive = strpos($body, 'INACT-A');
        $this->assertNotFalse($posActive);
        $this->assertNotFalse($posInactive);
        $this->assertLessThan($posInactive, $posActive, 'Active kitchens should appear before inactive ones.');
    }

    public function test_coordinate_persists_to_seven_decimal_precision(): void
    {
        $this->actingAs(User::factory()->owner()->create());

        $this->post('/kitchens', [
            'code' => 'KIT-COORD',
            'name' => 'Precise',
            'address' => 'Somewhere',
            'phone' => null,
            'latitude' => '-6.1234567',
            'longitude' => '106.7654321',
            'is_active' => '1',
        ])->assertRedirect(route('kitchens.index'));

        $kitchen = Kitchen::firstWhere('code', 'KIT-COORD');
        $this->assertSame('-6.1234567', (string) $kitchen->latitude);
        $this->assertSame('106.7654321', (string) $kitchen->longitude);
    }

    public function test_update_preserves_record_identity(): void
    {
        $kitchen = Kitchen::factory()->create(['code' => 'KIT-KEEP']);
        $originalId = $kitchen->id;
        $originalCreated = $kitchen->created_at;

        $this->actingAs(User::factory()->owner()->create());
        $this->put("/kitchens/{$kitchen->id}", [
            'code' => 'KIT-KEEP',
            'name' => 'Renamed',
            'address' => 'New Address',
            'phone' => null,
            'latitude' => '-6.9',
            'longitude' => '106.9',
            'is_active' => '1',
        ])->assertRedirect(route('kitchens.index'));

        $refreshed = Kitchen::find($originalId);
        $this->assertNotNull($refreshed);
        $this->assertSame($originalId, $refreshed->id);
        $this->assertSame((string) $originalCreated, (string) $refreshed->created_at);
    }

    public function test_pagination_works_when_records_exceed_page_size(): void
    {
        Kitchen::factory()->count(20)->create();
        $this->actingAs(User::factory()->owner()->create());

        $page1 = $this->get('/kitchens')->assertOk();
        $page2 = $this->get('/kitchens?page=2')->assertOk();

        $this->assertNotSame($page1->getContent(), $page2->getContent());
    }

    public function test_no_kitchen_deletion_route_exists(): void
    {
        $kitchen = Kitchen::factory()->create();
        $this->actingAs(User::factory()->owner()->create());

        $this->delete("/kitchens/{$kitchen->id}")->assertStatus(405);
        $this->assertDatabaseHas('kitchens', ['id' => $kitchen->id]);
    }

    public function test_deactivation_does_not_delete_the_record(): void
    {
        $kitchen = Kitchen::factory()->create(['code' => 'KEEP-ME']);
        $this->actingAs(User::factory()->owner()->create());

        $this->put("/kitchens/{$kitchen->id}", [
            'code' => 'KEEP-ME',
            'name' => 'Keep Me',
            'address' => 'Address',
            'phone' => null,
            'latitude' => '-6.9',
            'longitude' => '106.9',
            'is_active' => '0',
        ])->assertRedirect(route('kitchens.index'));

        $this->assertDatabaseHas('kitchens', ['id' => $kitchen->id, 'is_active' => false]);
    }
}
