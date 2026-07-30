<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Http\Controllers\TrackingController;
use App\Models\Delivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POST /track/sign-out: clears the tracking session and returns to the
 * form. The route is CSRF-protected via web middleware, so the standard
 * Laravel test client (which disables the middleware) exercises the
 * happy path.
 */
class TrackingSignOutTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_out_clears_session_and_redirects_to_form(): void
    {
        $delivery = Delivery::factory()->scheduled()->create();

        $response = $this
            ->withSession([TrackingController::SESSION_KEY => $delivery->id])
            ->post(route('tracking.signOut'));

        $response->assertRedirect(route('tracking.form'));
        $this->assertNull(session(TrackingController::SESSION_KEY));
    }

    public function test_sign_out_is_idempotent_without_session(): void
    {
        $response = $this->post(route('tracking.signOut'));

        $response->assertRedirect(route('tracking.form'));
        $this->assertNull(session(TrackingController::SESSION_KEY));
    }

    public function test_sign_out_regenerates_csrf_token(): void
    {
        $this->startSession();
        $originalToken = csrf_token();

        $this->post(route('tracking.signOut'));

        $this->assertNotSame($originalToken, csrf_token());
    }

    public function test_sign_out_does_not_delete_delivery(): void
    {
        $delivery = Delivery::factory()->scheduled()->create();

        $this
            ->withSession([TrackingController::SESSION_KEY => $delivery->id])
            ->post(route('tracking.signOut'));

        $this->assertDatabaseHas('deliveries', ['id' => $delivery->id]);
    }

    public function test_status_page_renders_sign_out_button(): void
    {
        $delivery = Delivery::factory()->scheduled()->create();

        $response = $this
            ->withSession([TrackingController::SESSION_KEY => $delivery->id])
            ->get(route('tracking.status'));

        $response->assertOk();
        $response->assertSee('Look up another delivery');
        $response->assertSee(route('tracking.signOut'), escape: false);
    }
}
