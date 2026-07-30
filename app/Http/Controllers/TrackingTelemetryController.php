<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Delivery\DeliveryStatus;
use App\Domain\Telemetry\LatestTelemetryProvider;
use App\Models\Delivery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON polling endpoint for the public customer tracking live-map.
 *
 * Route: GET /track/telemetry/latest
 *   - Middleware: `throttle:60,1` only (AR-57).
 *   - Authentication: session-scoped on `tracking.delivery_id`, the
 *     same key {@see TrackingController} sets after a successful
 *     receipt + phone-last-4 login. No auth guard is involved; the
 *     controller looks the session key up itself so a missing/stale
 *     value responds with 401 rather than a redirect.
 *   - Response body: `{delivery_id, status, latest}`. The customer
 *     surface omits `speed_kmh` and `heading_degrees`, and returns
 *     `latest: null` for any status other than `in_transit`.
 *   - Response codes: 200 (JSON body), 401 (no valid session), 429
 *     (throttle).
 */
class TrackingTelemetryController extends Controller
{
    public function __construct(
        private readonly LatestTelemetryProvider $provider,
    ) {
    }

    /**
     * Return the latest telemetry row for the currently session-bound
     * delivery, shaped for the customer surface (AR-57).
     *
     * The session key must resolve to a delivery in a "trackable"
     * status (`scheduled`, `in_transit`, `delivered`, `cancelled`);
     * draft rows are never trackable per AR-44. When the session key
     * is missing, malformed, or points to a non-trackable delivery,
     * the response is a JSON 401. The customer JS client treats 401
     * as "sign back in" and the outer tracking status page will guard
     * that transition on the next full page load.
     */
    public function latest(Request $request): JsonResponse
    {
        $sessionId = $request->session()->get(TrackingController::SESSION_KEY);

        if (! is_int($sessionId) && ! (is_string($sessionId) && ctype_digit($sessionId))) {
            return $this->unauthenticated();
        }

        $delivery = Delivery::query()
            ->whereKey((int) $sessionId)
            ->whereIn('status', [
                DeliveryStatus::Scheduled->value,
                DeliveryStatus::InTransit->value,
                DeliveryStatus::Delivered->value,
                DeliveryStatus::Cancelled->value,
            ])
            ->first();

        if ($delivery === null) {
            return $this->unauthenticated();
        }

        return response()->json($this->provider->forCustomer($delivery));
    }

    /**
     * Consistent 401 payload for any session mismatch. Kept small and
     * free of identifiers so a poll from a stale tab does not leak
     * information about which delivery ids exist.
     */
    private function unauthenticated(): JsonResponse
    {
        return response()->json([
            'message' => 'Tracking session expired. Please look up your delivery again.',
        ], 401);
    }
}
