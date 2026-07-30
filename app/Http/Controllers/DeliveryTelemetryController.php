<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\UserRole;
use App\Domain\Telemetry\LatestTelemetryProvider;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON polling endpoint for the internal (staff / owner / courier)
 * live-map view.
 *
 * Route: GET /deliveries/{delivery}/telemetry/latest
 *   - Middleware: `auth`, `active`, `role:owner,staff,courier`,
 *     `throttle:60,1` (AR-57).
 *   - Response body: `{delivery_id, status, latest}`. `latest` is either
 *     an object (`latitude`, `longitude`, `speed_kmh`, `heading_degrees`,
 *     `gps_timestamp`, `received_at`) or `null` when no telemetry rows
 *     exist for the delivery.
 *   - Response codes: 200 (JSON body), 403 (courier viewer that is not
 *     the assigned courier), 429 (throttle).
 *
 * The controller is intentionally thin: role membership is enforced by
 * route middleware, ownership by the guard inside `latest()`, and
 * shaping by {@see LatestTelemetryProvider}. There is no fallback to
 * kitchen or customer snapshot coordinates on this endpoint; the JS
 * client leaves the courier marker in its last-known position until a
 * subsequent poll returns fresh data.
 */
class DeliveryTelemetryController extends Controller
{
    public function __construct(
        private readonly LatestTelemetryProvider $provider,
    ) {
    }

    /**
     * Return the latest telemetry row for the given delivery, shaped
     * for the staff surface (AR-57). Couriers may only poll their own
     * assigned delivery; a mismatch responds with 403 rather than
     * silently returning `null` so client bugs surface as HTTP errors
     * during development.
     */
    public function latest(Request $request, Delivery $delivery): JsonResponse
    {
        $actor = $request->user();

        if ($actor instanceof User && $actor->role === UserRole::Courier) {
            $isAssigned = $delivery->courier_id !== null
                && (int) $delivery->courier_id === (int) $actor->getKey();

            if (! $isAssigned) {
                return response()->json([
                    'message' => 'You can only poll telemetry for deliveries assigned to you.',
                ], 403);
            }
        }

        return response()->json($this->provider->forStaff($delivery));
    }
}
