<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('example@example.com|127.0.0.1');
    }

    public function test_login_page_is_accessible_to_guests(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_authenticated_user_visiting_login_is_redirected_to_dashboard(): void
    {
        $user = User::factory()->staff()->create();

        $this->actingAs($user)
            ->get('/login')
            ->assertRedirect('/dashboard');
    }

    public function test_protected_dashboard_redirects_guests_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_active_owner_can_log_in(): void
    {
        User::factory()->owner()->create([
            'email' => 'owner@example.test',
            'password' => Hash::make('secret1234'),
        ]);

        $response = $this->post('/login', [
            'email' => 'owner@example.test',
            'password' => 'secret1234',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_active_staff_can_log_in(): void
    {
        User::factory()->staff()->create([
            'email' => 'staff@example.test',
            'password' => Hash::make('secret1234'),
        ]);

        $this->post('/login', [
            'email' => 'staff@example.test',
            'password' => 'secret1234',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_active_courier_can_log_in(): void
    {
        User::factory()->courier()->create([
            'email' => 'courier@example.test',
            'password' => Hash::make('secret1234'),
        ]);

        $this->post('/login', [
            'email' => 'courier@example.test',
            'password' => 'secret1234',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    }

    public function test_wrong_password_produces_generic_error(): void
    {
        User::factory()->staff()->create([
            'email' => 'user@example.test',
            'password' => Hash::make('correcthorse'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'user@example.test',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame([__('auth.failed')], session('errors')->get('email'));
    }

    public function test_unknown_email_produces_same_generic_error(): void
    {
        $response = $this->from('/login')->post('/login', [
            'email' => 'nobody@example.test',
            'password' => 'any-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame([__('auth.failed')], session('errors')->get('email'));
    }

    public function test_inactive_account_cannot_log_in(): void
    {
        User::factory()->staff()->inactive()->create([
            'email' => 'inactive@example.test',
            'password' => Hash::make('secret1234'),
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'inactive@example.test',
            'password' => 'secret1234',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertSame([__('auth.failed')], session('errors')->get('email'));
    }

    public function test_successful_login_regenerates_session_id(): void
    {
        User::factory()->staff()->create([
            'email' => 'sess@example.test',
            'password' => Hash::make('secret1234'),
        ]);

        $this->startSession();
        $before = session()->getId();

        $this->post('/login', [
            'email' => 'sess@example.test',
            'password' => 'secret1234',
        ]);

        $this->assertNotSame($before, session()->getId());
    }

    public function test_logout_ends_authentication(): void
    {
        $user = User::factory()->staff()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }
}
