<?php

declare(strict_types=1);

namespace App\Domain\Delivery;

use InvalidArgumentException;

/**
 * Delivery fee calculator (AR-04, AR-29 revised).
 *
 * Stateless. Reads pricing constants from config('pricing') on every
 * call so that tests can override values at runtime via
 * config()->set(...). Performs no I/O and no distance calculation.
 *
 * Algorithm:
 *   raw     = distanceKm * rate_per_km_rupiah                (float)
 *   rounded = round(raw / step, 0, PHP_ROUND_HALF_UP) * step (int)
 *   fee     = max(minimum_fee_rupiah, rounded)               (int)
 *
 * The divide/multiply rounding form is preferred over round($raw, -N)
 * because it avoids negative-precision edge cases when the step is
 * not a power of ten (e.g. 500 rupiah).
 */
final class PricingCalculator
{
    /**
     * Compute the whole-rupiah delivery fee for the given distance.
     *
     * @throws InvalidArgumentException when $distanceKm is negative.
     */
    public function feeForDistanceKm(float $distanceKm): int
    {
        if ($distanceKm < 0.0) {
            throw new InvalidArgumentException(
                sprintf('Distance %F km is negative.', $distanceKm),
            );
        }

        $minimum = (int) config('pricing.minimum_fee_rupiah', 5000);
        $rate = (int) config('pricing.rate_per_km_rupiah', 2000);
        $step = (int) config('pricing.fee_rounding_step_rupiah', 100);

        if ($step <= 0) {
            throw new InvalidArgumentException(
                'pricing.fee_rounding_step_rupiah must be a positive integer.',
            );
        }

        $raw = $distanceKm * $rate;
        $rounded = (int) (round($raw / $step, 0, PHP_ROUND_HALF_UP) * $step);

        return max($minimum, $rounded);
    }
}
