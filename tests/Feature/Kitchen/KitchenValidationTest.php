<?php

declare(strict_types=1);

namespace Tests\Feature\Kitchen;

use App\Models\Kitchen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->owner()->create());
    }

    private function valid(array $overrides = []): array
    {
        return array_replace([
            'code' => 'KIT-001',
            'name' => 'Central Kitchen',
            'address' => 'Jl. Merdeka 1',
            'phone' => '+628123456789',
            'latitude' => '-6.9175000',
            'longitude' => '106.9270000',
            'is_active' => '1',
        ], $overrides);
    }

    public function test_kitchen_code_is_normalized_to_uppercase(): void
    {
        $this->post('/kitchens', $this->valid(['code' => 'kit-lower']))
            ->assertRedirect(route('kitchens.index'));

        $this->assertDatabaseHas('kitchens', ['code' => 'KIT-LOWER']);
    }

    public function test_leading_and_trailing_code_whitespace_are_removed(): void
    {
        $this->post('/kitchens', $this->valid(['code' => '  kit-trim  ']))
            ->assertRedirect(route('kitchens.index'));

        $this->assertDatabaseHas('kitchens', ['code' => 'KIT-TRIM']);
    }

    public function test_duplicate_code_is_rejected(): void
    {
        Kitchen::factory()->create(['code' => 'KIT-DUP']);
        $this->post('/kitchens', $this->valid(['code' => 'KIT-DUP']))
            ->assertSessionHasErrors('code');
    }

    public function test_update_permits_retaining_the_same_code(): void
    {
        $kitchen = Kitchen::factory()->create(['code' => 'KIT-KEEP']);
        $this->put("/kitchens/{$kitchen->id}", $this->valid(['code' => 'KIT-KEEP', 'name' => 'Still Same']))
            ->assertRedirect(route('kitchens.index'));

        $this->assertDatabaseHas('kitchens', ['code' => 'KIT-KEEP', 'name' => 'Still Same']);
    }

    public function test_code_with_internal_spaces_is_rejected(): void
    {
        $this->post('/kitchens', $this->valid(['code' => 'KIT 01']))
            ->assertSessionHasErrors('code');
    }

    public function test_code_with_underscore_is_rejected(): void
    {
        $this->post('/kitchens', $this->valid(['code' => 'KIT_01']))
            ->assertSessionHasErrors('code');
    }

    public function test_missing_name_is_rejected(): void
    {
        $this->post('/kitchens', $this->valid(['name' => '']))
            ->assertSessionHasErrors('name');
    }

    public function test_missing_address_is_rejected(): void
    {
        $this->post('/kitchens', $this->valid(['address' => '']))
            ->assertSessionHasErrors('address');
    }

    public function test_alphabetic_phone_is_rejected(): void
    {
        $this->post('/kitchens', $this->valid(['phone' => 'call-me']))
            ->assertSessionHasErrors('phone');
    }

    public function test_valid_phone_formats_are_accepted(): void
    {
        $this->post('/kitchens', $this->valid(['code' => 'KIT-P1', 'phone' => '(021) 555-1234']))
            ->assertRedirect(route('kitchens.index'));

        $this->assertDatabaseHas('kitchens', ['code' => 'KIT-P1']);
    }

    public function test_latitude_below_range_is_rejected(): void
    {
        $this->post('/kitchens', $this->valid(['latitude' => '-91']))
            ->assertSessionHasErrors('latitude');
    }

    public function test_latitude_above_range_is_rejected(): void
    {
        $this->post('/kitchens', $this->valid(['latitude' => '91']))
            ->assertSessionHasErrors('latitude');
    }

    public function test_longitude_below_range_is_rejected(): void
    {
        $this->post('/kitchens', $this->valid(['longitude' => '-181']))
            ->assertSessionHasErrors('longitude');
    }

    public function test_longitude_above_range_is_rejected(): void
    {
        $this->post('/kitchens', $this->valid(['longitude' => '181']))
            ->assertSessionHasErrors('longitude');
    }

    public function test_missing_latitude_is_rejected(): void
    {
        $this->post('/kitchens', $this->valid(['latitude' => '']))
            ->assertSessionHasErrors('latitude');
    }

    public function test_missing_longitude_is_rejected(): void
    {
        $this->post('/kitchens', $this->valid(['longitude' => '']))
            ->assertSessionHasErrors('longitude');
    }

    public function test_boolean_active_status_is_normalized(): void
    {
        $this->post('/kitchens', $this->valid(['code' => 'KIT-B1', 'is_active' => 'yes']))
            ->assertRedirect(route('kitchens.index'));
        $this->assertDatabaseHas('kitchens', ['code' => 'KIT-B1', 'is_active' => true]);

        $this->post('/kitchens', $this->valid(['code' => 'KIT-B2', 'is_active' => '0']))
            ->assertRedirect(route('kitchens.index'));
        $this->assertDatabaseHas('kitchens', ['code' => 'KIT-B2', 'is_active' => false]);
    }

    public function test_unvalidated_extra_fields_are_ignored(): void
    {
        $this->post('/kitchens', $this->valid([
            'code' => 'KIT-X',
            'created_at' => '1900-01-01 00:00:00',
            'extra_column' => 'ignored',
        ]))->assertRedirect(route('kitchens.index'));

        $kitchen = Kitchen::firstWhere('code', 'KIT-X');
        $this->assertNotNull($kitchen);
        $this->assertGreaterThan('2000-01-01', (string) $kitchen->created_at);
    }
}
