<?php

declare(strict_types=1);

/*
 * Delivery pricing configuration.
 *
 * Approved by AR-04 (fee formula), AR-29 (rounding rule), and AR-32
 * (Haversine authority). Values are read at request time by
 * App\Domain\Delivery\PricingCalculator so that tests can override
 * them via config()->set() without process restarts.
 *
 * Earth radius is a physical constant embedded in
 * App\Domain\Delivery\DistanceCalculator and is intentionally not
 * configurable.
 */

return [

    /*
     * Minimum fee applied AFTER rounding. Distances that price out
     * below this floor are charged the floor. Whole rupiah.
     */
    'minimum_fee_rupiah' => (int) env('PRICING_MINIMUM_FEE_RUPIAH', 5000),

    /*
     * Rate applied to the Haversine distance in kilometres. Whole
     * rupiah per kilometre. The raw product is then rounded to the
     * nearest `fee_rounding_step_rupiah` (half-up) and floored by
     * `minimum_fee_rupiah`.
     */
    'rate_per_km_rupiah' => (int) env('PRICING_RATE_PER_KM_RUPIAH', 2000),

    /*
     * Rounding granularity applied to the raw distance-based charge
     * BEFORE the minimum-fee floor. `PHP_ROUND_HALF_UP` semantics
     * (see AR-29 revised).
     */
    'fee_rounding_step_rupiah' => (int) env('PRICING_FEE_ROUNDING_STEP_RUPIAH', 100),

];
