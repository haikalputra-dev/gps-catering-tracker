<?php

declare(strict_types=1);

namespace Tests\Feature\Kitchen;

use App\Models\Kitchen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenManagementTest extends TestCase
{
    use RefreshDatabase;

    private function baseAttributes(array $overrides = []): array
    {
        return array_replace([
            'code' => 'KIT-001',
            'name' => 'Central Kitchen',
            'address' => 'Jl. Merdeka 1, Sukabumi',
            'phone' => '+628123456789',
            'latitude' => '-6.9175000',
            'longitude' => '106.9270000',
            'is_active' => '1',
        ], $overrides);
    }

    public function test_owner_can_create_active_kitchen(): void
    {
        $this->actingAs(User::factory()->owner()->create());

        $response = $this->post('/kitchens', $this->baseAttributes());

        $response->assertRedirect(route('kitchens.index'));
        $this->assertDatabaseHas('kitchens', [
            'code' => 'KIT-001',
            'name' => 'Central Kitchen',
            'is_active' => true,
        ]);
    }

    public function test_staff_can_create_active_kitchen(): void
    {
        $this->actingAs(User::factory()->staff()->create());

        $this->post('/kitchens', $this->baseAttributes(['code' => 'KIT-002']))
            ->assertRedirect(route('kitchens.index'));

        $this->assertDatabaseHas('kitchens', ['code' => 'KIT-002']);
    }

    public function test_owner_can_create_initially_inactive_kitchen(): void
    {
        $this->actingAs(User::factory()->owner()->create());

        $this->post('/kitchens', $this->baseAttributes([
            'code' => 'KIT-INACTIVE',
            'is_active' => '0',
        ]))->assertRedirect(route('kitchens.index'));

        $this->assertDatabaseHas('kitchens', [
            'code' => 'KIT-INACTIVE',
            'is_active' => false,
        ]);
    }

    public function test_staff_can_edit_a_kitchen(): void
    {
        $kitchen = Kitchen::factory()->create(['code' => 'KIT-EDIT']);
        $this->actingAs(User::factory()->staff()->create());

        $this->put("/kitchens/{$kitchen->id}", $this->baseAttributes([
            'code' => 'KIT-EDIT',
            'name' => 'Renamed Kitchen',
        ]))->assertRedirect(route('kitchens.index'));

        $this->assertDatabaseHas('kitchens', [
            'id' => $kitchen->id,
            'name' => 'Renamed Kitchen',
        ]);
    }

    public function test_owner_can_deactivate_and_reactivate_a_kitchen(): void
    {
        $kitchen = Kitchen::factory()->create(['code' => 'KIT-TOGGLE']);
        $this->actingAs(User::factory()->owner()->create());

        $this->put("/kitchens/{$kitchen->id}", $this->baseAttributes([
            'code' => 'KIT-TOGGLE',
            'is_active' => '0',
        ]))->assertRedirect(route('kitchens.index'));

        $this->assertDatabaseHas('kitchens', [
            'id' => $kitchen->id,
            'is_active' => false,
        ]);

        $this->put("/kitchens/{$kitchen->id}", $this->baseAttributes([
            'code' => 'KIT-TOGGLE',
            'is_active' => '1',
        ]))->assertRedirect(route('kitchens.index'));

        $this->assertDatabaseHas('kitchens', [
            'id' => $kitchen->id,
            'is_active' => true,
        ]);
    }
}
