<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveAccountMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_reach_dashboard(): void
    {
        $user = User::factory()->staff()->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('staff.dashboard'));
    }

    public function test_user_deactivated_after_login_is_logged_out_on_next_request(): void
    {
        $user = User::factory()->staff()->create();

        $this->actingAs($user);
        $user->update(['is_active' => false]);

        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_inactive_redirect_does_not_leak_internal_details(): void
    {
        $user = User::factory()->staff()->inactive()->create();

        $response = $this->actingAs($user)->followingRedirects()->get('/dashboard');

        $response->assertSee('Your account is not available.');
        $response->assertDontSee('deactivated');
        $response->assertDontSee('disabled');
        $this->assertGuest();
    }
}
