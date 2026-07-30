<?php

declare(strict_types=1);

namespace Tests\Feature\Kitchen;

use App\Models\Kitchen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KitchenAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_index(): void
    {
        $this->get('/kitchens')->assertRedirect('/login');
    }

    public function test_guest_is_redirected_from_create(): void
    {
        $this->get('/kitchens/create')->assertRedirect('/login');
    }

    public function test_owner_can_access_index(): void
    {
        $this->actingAs(User::factory()->owner()->create());
        $this->get('/kitchens')->assertOk();
    }

    public function test_owner_can_access_create(): void
    {
        $this->actingAs(User::factory()->owner()->create());
        $this->get('/kitchens/create')->assertOk();
    }

    public function test_staff_can_access_index(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $this->get('/kitchens')->assertOk();
    }

    public function test_staff_can_access_create(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $this->get('/kitchens/create')->assertOk();
    }

    public function test_courier_receives_403_from_index(): void
    {
        $this->actingAs(User::factory()->courier()->create());
        $this->get('/kitchens')->assertForbidden();
    }

    public function test_courier_receives_403_from_create(): void
    {
        $this->actingAs(User::factory()->courier()->create());
        $this->get('/kitchens/create')->assertForbidden();
    }

    public function test_courier_receives_403_from_store(): void
    {
        $this->actingAs(User::factory()->courier()->create());
        $this->post('/kitchens', [])->assertForbidden();
    }

    public function test_courier_receives_403_from_edit(): void
    {
        $kitchen = Kitchen::factory()->create();
        $this->actingAs(User::factory()->courier()->create());
        $this->get("/kitchens/{$kitchen->id}/edit")->assertForbidden();
    }

    public function test_courier_receives_403_from_update(): void
    {
        $kitchen = Kitchen::factory()->create();
        $this->actingAs(User::factory()->courier()->create());
        $this->put("/kitchens/{$kitchen->id}", [])->assertForbidden();
    }

    public function test_inactive_owner_session_is_rejected(): void
    {
        $user = User::factory()->owner()->create();
        $this->actingAs($user);
        $user->forceFill(['is_active' => false])->save();

        $response = $this->get('/kitchens');
        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
