<?php

declare(strict_types=1);

namespace Tests\Feature\Tracking;

use App\Domain\Delivery\DeliveryStatus;
use App\Http\Controllers\TrackingController;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Kitchen;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /track: the public receipt-entry form.
 *
 * The form is available to unauthenticated visitors, requires no
 * customer account, and short-circuits to the status page when a
 * valid tracking session is already active. Stale session values
 * (deleted rows, drafts, non-numeric junk) must be cleared without
 * throwing.
 */
class TrackingFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_is_public(): void
    {
        $response = $this->get(route('tracking.form'));

        $response->assertOk();
        $response->assertViewIs('tracking.form');
        $response->assertSee('Track your delivery');
        $response->assertSee('Receipt number');
        $response->assertSee('Last 4 digits of your phone');
    }

    public function test_form_uses_public_layout_not_authenticated_header(): void
    {
        $response = $this->get(route('tracking.form'));

        $response->assertOk();
        // Public layout must not render the authenticated navigation.
        $response->assertDontSee('Deliveries');
        $response->assertDontSee('Kitchens');
        $response->assertDontSee('User Accounts');
        $response->assertDontSee('Log out');
    }

    public function test_form_redirects_to_status_when_session_has_valid_delivery(): void
    {
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create();

        $response = $this
            ->withSession([TrackingController::SESSION_KEY => $delivery->id])
            ->get(route('tracking.form'));

        $response->assertRedirect(route('tracking.status'));
    }

    public function test_form_clears_stale_session_for_missing_delivery(): void
    {
        $response = $this
            ->withSession([TrackingController::SESSION_KEY => 999_999])
            ->get(route('tracking.form'));

        $response->assertOk();
        $response->assertViewIs('tracking.form');
        $this->assertNull(session(TrackingController::SESSION_KEY));
    }

    public function test_form_clears_stale_session_for_draft_delivery(): void
    {
        // A draft in the session is not trackable and must be cleared.
        $draft = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->create([
                'status' => DeliveryStatus::Draft->value,
            ]);

        $response = $this
            ->withSession([TrackingController::SESSION_KEY => $draft->id])
            ->get(route('tracking.form'));

        $response->assertOk();
        $response->assertViewIs('tracking.form');
        $this->assertNull(session(TrackingController::SESSION_KEY));
    }

    public function test_form_tolerates_non_numeric_session_value(): void
    {
        $response = $this
            ->withSession([TrackingController::SESSION_KEY => 'not-an-id'])
            ->get(route('tracking.form'));

        $response->assertOk();
        $response->assertViewIs('tracking.form');
    }

    public function test_page_is_noindex(): void
    {
        // Tracking pages must not be indexed by search engines.
        $response = $this->get(route('tracking.form'));

        $response->assertOk();
        $response->assertSee('noindex', escape: false);
    }
}
