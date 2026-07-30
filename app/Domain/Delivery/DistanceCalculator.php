<?php

declare(strict_types=1);

namespace App\Domain\Delivery;

use InvalidArgumentException;

/**
 * Haversine geodesic distance calculator (AR-32).
 *
 * Pure, stateless, deterministic. Reads no configuration and touches
 * no I/O. The mean Earth radius is a physical constant embedded here
 * and is intentionally NOT configurable.
 *
 * Formula (great-circle distance):
 *   phi1 = deg2rad(lat1)
 *   phi2 = deg2rad(lat2)
 *   dPhi = deg2rad(lat2 - lat1)
 *   dLambda = deg2rad(lng2 - lng1)
 *   a = sin(dPhi/2)^2 + cos(phi1) * cos(phi2) * sin(dLambda/2)^2
 *   c = 2 * atan2(sqrt(a), sqrt(1 - a))
 *   d = R * c
 *
 * Compliance note: Haversine returns the geodesic (straight-line-over-
 * sphere) distance between two points. Actual road distance may be
 * longer due to terrain and road networks. Road-network routing is
 * out of scope for this project.
 */
final class DistanceCalculator
{
    /**
     * Mean Earth radius in kilometres, IUGG mean value.
     * See docs/decisions/ADR-013-haversine-and-fee-formula.md.
     */
    public const EARTH_RADIUS_KM = 6371.0088;

    /**
     * Compute the geodesic distance in kilometres between two points.
     *
     * @throws InvalidArgumentException when any coordinate is out of range.
     */
    public function between(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $this->assertLatitude($lat1);
        $this->assertLatitude($lat2);
        $this->assertLongitude($lng1);
        $this->assertLongitude($lng2);

        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $dPhi = deg2rad($lat2 - $lat1);
        $dLambda = deg2rad($lng2 - $lng1);

        $sinDPhi = sin($dPhi / 2.0);
        $sinDLambda = sin($dLambda / 2.0);

        $a = ($sinDPhi * $sinDPhi)
            + cos($phi1) * cos($phi2) * ($sinDLambda * $sinDLambda);

        // Clamp defensively to protect atan2 from floating-point drift
        // at antipodal distances (a can round slightly above 1.0).
        if ($a > 1.0) {
            $a = 1.0;
        } elseif ($a < 0.0) {
            $a = 0.0;
        }

        $c = 2.0 * atan2(sqrt($a), sqrt(1.0 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }

    private function assertLatitude(float $lat): void
    {
        if ($lat < -90.0 || $lat > 90.0) {
            throw new InvalidArgumentException(
                sprintf('Latitude %F is outside the range [-90, 90].', $lat),
            );
        }
    }

    private function assertLongitude(float $lng): void
    {
        if ($lng < -180.0 || $lng > 180.0) {
            throw new InvalidArgumentException(
                sprintf('Longitude %F is outside the range [-180, 180].', $lng),
            );
        }
    }
}
