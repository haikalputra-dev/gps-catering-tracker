<?php

declare(strict_types=1);

namespace App\Domain\Telemetry;

/**
 * Small helper that produces a straight-line sequence of intermediate
 * GPS points between two endpoints.
 *
 * Used by `telemetry:simulate` (AR-54) to synthesise ESP32-style pings
 * for a courier moving from the kitchen snapshot to the customer
 * snapshot along a linear path.
 *
 * The interpolation is naïve linear-in-degrees, not great-circle. Over
 * the intra-city distances this prototype targets (single-digit km),
 * the divergence from a true geodesic is well below the accuracy of
 * the underlying GPS receiver.
 */
final class PathInterpolator
{
    /**
     * Interpolate a point at parameter `t` between the two endpoints.
     *
     * `t = 0.0` returns the start point; `t = 1.0` returns the end
     * point; intermediate values return the linearly interpolated
     * position. Values outside `[0, 1]` are clamped so callers cannot
     * accidentally simulate a courier that overshoots the customer.
     *
     * @return array{float, float} `[latitude, longitude]`
     */
    public static function interpolate(
        float $startLat,
        float $startLng,
        float $endLat,
        float $endLng,
        float $t,
    ): array {
        $clamped = max(0.0, min(1.0, $t));

        $lat = $startLat + ($endLat - $startLat) * $clamped;
        $lng = $startLng + ($endLng - $startLng) * $clamped;

        return [$lat, $lng];
    }

    /**
     * Compute the initial bearing (degrees, 0..360) from start to end.
     *
     * Used as the reported `heading_degrees` for simulated pings; the
     * value stays constant across a straight-line segment. Degenerate
     * inputs (zero-length segment) return `0.0`.
     */
    public static function bearing(
        float $startLat,
        float $startLng,
        float $endLat,
        float $endLng,
    ): float {
        $lat1 = deg2rad($startLat);
        $lat2 = deg2rad($endLat);
        $deltaLng = deg2rad($endLng - $startLng);

        $y = sin($deltaLng) * cos($lat2);
        $x = cos($lat1) * sin($lat2)
            - sin($lat1) * cos($lat2) * cos($deltaLng);

        if ($y === 0.0 && $x === 0.0) {
            return 0.0;
        }

        $bearing = rad2deg(atan2($y, $x));

        return fmod($bearing + 360.0, 360.0);
    }

    /**
     * Compute the great-circle distance between two points, in metres.
     *
     * Uses the Haversine formula with the same Earth-radius constant
     * as `App\Domain\Delivery\DistanceCalculator` (6_371_000 m).
     */
    public static function distanceMeters(
        float $startLat,
        float $startLng,
        float $endLat,
        float $endLng,
    ): float {
        $earthRadiusMeters = 6_371_000.0;

        $lat1 = deg2rad($startLat);
        $lat2 = deg2rad($endLat);
        $deltaLat = deg2rad($endLat - $startLat);
        $deltaLng = deg2rad($endLng - $startLng);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusMeters * $c;
    }

    /**
     * Convert a jitter magnitude expressed in metres into a coarse
     * degrees offset near a reference latitude.
     *
     * The conversion uses the classic ~111_320 m per degree of
     * latitude and adjusts longitude by `cos(latitude)`. This is
     * sufficient for adding a small random walk to simulated pings;
     * the simulator itself picks the sign and magnitude per axis.
     *
     * @return array{float, float} `[deltaLat, deltaLng]`
     */
    public static function jitterOffsetDegrees(
        float $referenceLat,
        float $meters,
    ): array {
        if ($meters <= 0.0) {
            return [0.0, 0.0];
        }

        $metersPerDegreeLat = 111_320.0;
        $metersPerDegreeLng = 111_320.0 * cos(deg2rad($referenceLat));
        if ($metersPerDegreeLng < 1.0) {
            $metersPerDegreeLng = 1.0;
        }

        return [
            $meters / $metersPerDegreeLat,
            $meters / $metersPerDegreeLng,
        ];
    }
}
