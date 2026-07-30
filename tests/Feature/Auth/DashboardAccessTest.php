<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_is_redirected_to_owner_dashboard(): void
    {
        $user = User::factory()->owner()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('owner.dashboard'));

        $this->actingAs($user)
            ->get('/owner/dashboard')
            ->assertOk();
    }

    public function test_staff_is_redirected_to_staff_dashboard(): void
    {
        $user = User::factory()->staff()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('staff.dashboard'));

        $this->actingAs($user)
            ->get('/staff/dashboard')
            ->assertOk();
    }

    public function test_courier_is_redirected_to_courier_dashboard(): void
    {
        $user = User::factory()->courier()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('courier.dashboard'));

        $this->actingAs($user)
            ->get('/courier/dashboard')
            ->assertOk();
    }

    public function test_owner_receives_403_from_staff_and_courier_dashboards(): void
    {
        $owner = User::factory()->owner()->create();

        $this->actingAs($owner)->get('/staff/dashboard')->assertForbidden();
        $this->actingAs($owner)->get('/courier/dashboard')->assertForbidden();
    }

    public function test_staff_receives_403_from_owner_and_courier_routes(): void
    {
        $staff = User::factory()->staff()->create();

        $this->actingAs($staff)->get('/owner/dashboard')->assertForbidden();
        $this->actingAs($staff)->get('/courier/dashboard')->assertForbidden();
        $this->actingAs($staff)->get('/owner/users')->assertForbidden();
    }

    public function test_courier_receives_403_from_owner_and_staff_routes(): void
    {
        $courier = User::factory()->courier()->create();

        $this->actingAs($courier)->get('/owner/dashboard')->assertForbidden();
        $this->actingAs($courier)->get('/staff/dashboard')->assertForbidden();
        $this->actingAs($courier)->get('/owner/users')->assertForbidden();
    }
}
