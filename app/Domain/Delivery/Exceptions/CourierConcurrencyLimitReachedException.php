<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Exceptions;

use RuntimeException;

/**
 * Thrown when scheduling would push the assigned courier over the
 * configured per-courier concurrency limit (AR-34). Enforced by
 * `DeliveryScheduler` in the `draft → scheduled` transition alongside
 * the system-wide limit; the two limits are enforced independently.
 */
class CourierConcurrencyLimitReachedException extends RuntimeException
{
    public static function forLimit(int $limit): self
    {
        return new self(sprintf(
            'Delivery cannot be scheduled: courier already has %d active deliveries.',
            $limit,
        ));
    }
}
