<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_index(): void
    {
        $this->get('/customers')->assertRedirect('/login');
    }

    public function test_guest_is_redirected_from_create(): void
    {
        $this->get('/customers/create')->assertRedirect('/login');
    }

    public function test_owner_can_access_index(): void
    {
        $this->actingAs(User::factory()->owner()->create());
        $this->get('/customers')->assertOk();
    }

    public function test_owner_can_access_create(): void
    {
        $this->actingAs(User::factory()->owner()->create());
        $this->get('/customers/create')->assertOk();
    }

    public function test_staff_can_access_index(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $this->get('/customers')->assertOk();
    }

    public function test_staff_can_access_create(): void
    {
        $this->actingAs(User::factory()->staff()->create());
        $this->get('/customers/create')->assertOk();
    }

    public function test_courier_receives_403_from_index(): void
    {
        $this->actingAs(User::factory()->courier()->create());
        $this->get('/customers')->assertForbidden();
    }

    public function test_courier_receives_403_from_create(): void
    {
        $this->actingAs(User::factory()->courier()->create());
        $this->get('/customers/create')->assertForbidden();
    }

    public function test_courier_receives_403_from_store(): void
    {
        $this->actingAs(User::factory()->courier()->create());
        $this->post('/customers', [])->assertForbidden();
    }

    public function test_courier_receives_403_from_edit(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->courier()->create());
        $this->get("/customers/{$customer->id}/edit")->assertForbidden();
    }

    public function test_courier_receives_403_from_update(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs(User::factory()->courier()->create());
        $this->put("/customers/{$customer->id}", [])->assertForbidden();
    }

    public function test_inactive_owner_session_is_rejected(): void
    {
        $user = User::factory()->owner()->create();
        $this->actingAs($user);
        $user->forceFill(['is_active' => false])->save();

        $response = $this->get('/customers');
        $response->assertRedirect('/login');
        $this->assertGuest();
    }
}
