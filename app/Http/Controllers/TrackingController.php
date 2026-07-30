<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Delivery\DeliveryStatus;
use App\Domain\Tracking\TrackingAuthenticator;
use App\Http\Requests\Tracking\TrackingAuthenticateRequest;
use App\Models\Delivery;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Customer-facing tracking surface (Packet 10).
 *
 * Four actions map to four public routes:
 *   - GET  /track            -> form()          name: tracking.form
 *   - POST /track            -> authenticate()  name: tracking.authenticate
 *   - GET  /track/status     -> status()        name: tracking.status
 *   - POST /track/sign-out   -> signOut()       name: tracking.signOut
 *
 * The controller never performs any operation on the delivery beyond
 * reading and never exposes administrative fields to the view.
 */
class TrackingController extends Controller
{
    /**
     * Session key that records the currently authenticated tracking
     * delivery id. Kept as a constant so tests and other actions read
     * the same name.
     */
    public const SESSION_KEY = 'tracking.delivery_id';

    /**
     * GET /track: render the receipt + phone-last-4 form. When the
     * session already holds a valid tracking id, redirect straight to
     * the status page instead of re-showing the form.
     */
    public function form(Request $request): View|RedirectResponse
    {
        $sessionId = $request->session()->get(self::SESSION_KEY);

        if (is_int($sessionId) || (is_string($sessionId) && ctype_digit($sessionId))) {
            $delivery = $this->loadTrackableDelivery((int) $sessionId);

            if ($delivery !== null) {
                return redirect()->route('tracking.status');
            }

            // Session key points to something no longer trackable (a
            // draft, a deleted row, or a stale identifier). Clear it
            // so subsequent requests don't loop through the redirect.
            $request->session()->forget(self::SESSION_KEY);
        }

        return view('tracking.form');
    }

    /**
     * POST /track: validate the payload, run the domain authenticator,
     * and either set the session key or return a generic error.
     */
    public function authenticate(
        TrackingAuthenticateRequest $request,
        TrackingAuthenticator $authenticator,
    ): RedirectResponse {
        $delivery = $authenticator->attempt(
            (string) $request->input('receipt_number', ''),
            (string) $request->input('phone_last_four', ''),
        );

        if ($delivery === null) {
            return redirect()
                ->route('tracking.form')
                ->withInput($request->only(['receipt_number']))
                ->withErrors(['form' => TrackingAuthenticateRequest::GENERIC_ERROR]);
        }

        // Regenerate the session identifier to prevent session
        // fixation now that the session carries an authenticated
        // tracking scope (AR-42 revised).
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $delivery->getKey());

        return redirect()->route('tracking.status');
    }

    /**
     * GET /track/status: render the customer-facing status page for
     * the delivery in the session. Any missing / stale / draft session
     * value redirects back to the form with a flashed info message.
     */
    public function status(Request $request): View|RedirectResponse
    {
        $sessionId = $request->session()->get(self::SESSION_KEY);

        if (! is_int($sessionId) && ! (is_string($sessionId) && ctype_digit($sessionId))) {
            return redirect()
                ->route('tracking.form')
                ->with('info', 'Please enter your receipt to view tracking status.');
        }

        $delivery = $this->loadTrackableDelivery((int) $sessionId);

        if ($delivery === null) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()
                ->route('tracking.form')
                ->with('info', 'Please enter your receipt to view tracking status.');
        }

        // The `courier` relation is only used when the delivery is
        // in-transit; loading it always keeps the view free of extra
        // queries when it is needed.
        $delivery->loadMissing('courier');

        return view('tracking.status', ['delivery' => $delivery]);
    }

    /**
     * POST /track/sign-out: clear the tracking session key and
     * regenerate the CSRF token. The link is labelled
     * "Look up another delivery" in the view.
     */
    public function signOut(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);
        $request->session()->regenerateToken();

        return redirect()->route('tracking.form');
    }

    /**
     * Fetch a delivery matching the given id, but only if it is in one
     * of the four trackable statuses (AR-44). Draft, deleted-and-
     * poisoned session values, and any non-matching id return null.
     */
    private function loadTrackableDelivery(int $id): ?Delivery
    {
        return Delivery::query()
            ->whereKey($id)
            ->whereIn('status', [
                DeliveryStatus::Scheduled->value,
                DeliveryStatus::InTransit->value,
                DeliveryStatus::Delivered->value,
                DeliveryStatus::Cancelled->value,
            ])
            ->first();
    }
}
