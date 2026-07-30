<?php

declare(strict_types=1);

namespace Tests\Feature\Delivery;

use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Kitchen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function draft(User $creator): Delivery
    {
        return Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->create(['created_by_user_id' => $creator->id]);
    }

    public function test_guest_redirected_from_index(): void
    {
        $this->get('/deliveries')->assertRedirect('/login');
    }

    public function test_guest_redirected_from_show(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = $this->draft($owner);

        $this->get("/deliveries/{$delivery->id}")->assertRedirect('/login');
    }

    public function test_owner_can_access_index(): void
    {
        $this->actingAs(User::factory()->owner()->create());
        $this->get('/deliveries')->assertOk();
    }

    public function test_staff_can_access_index(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $this->get('/deliveries')->assertOk();
    }

    public function test_courier_receives_403_from_index(): void
    {
        $this->actingAs(User::factory()->courier()->create());
        $this->get('/deliveries')->assertForbidden();
    }

    public function test_courier_receives_403_from_create(): void
    {
        $this->actingAs(User::factory()->courier()->create());
        $this->get('/deliveries/create')->assertForbidden();
    }

    public function test_courier_receives_403_from_store(): void
    {
        $this->actingAs(User::factory()->courier()->create());
        $this->post('/deliveries', [])->assertForbidden();
    }

    public function test_courier_receives_403_from_show(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = $this->draft($owner);

        $this->actingAs(User::factory()->courier()->create());
        $this->get("/deliveries/{$delivery->id}")->assertForbidden();
    }

    public function test_courier_receives_403_from_edit(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = $this->draft($owner);

        $this->actingAs(User::factory()->courier()->create());
        $this->get("/deliveries/{$delivery->id}/edit")->assertForbidden();
    }

    public function test_courier_receives_403_from_update(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = $this->draft($owner);

        $this->actingAs(User::factory()->courier()->create());
        $this->put("/deliveries/{$delivery->id}", [])->assertForbidden();
    }

    public function test_courier_receives_403_from_schedule(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = $this->draft($owner);

        $this->actingAs(User::factory()->courier()->create());
        $this->post("/deliveries/{$delivery->id}/schedule")->assertForbidden();
    }

    public function test_courier_receives_403_from_cancel(): void
    {
        $owner = User::factory()->owner()->create();
        $delivery = $this->draft($owner);

        $this->actingAs(User::factory()->courier()->create());
        $this->post("/deliveries/{$delivery->id}/cancel", [
            'cancellation_reason' => 'blocked route',
        ])->assertForbidden();
    }

    public function test_inactive_owner_session_is_rejected(): void
    {
        $user = User::factory()->owner()->create();
        $this->actingAs($user);
        $user->forceFill(['is_active' => false])->save();

        $this->get('/deliveries')->assertRedirect('/login');
        $this->assertGuest();
    }
}
