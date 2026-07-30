<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Exceptions;

use RuntimeException;

/**
 * Thrown when scheduling a draft would push the number of non-terminal
 * deliveries past `config('delivery.max_concurrent_active')` (AR-27).
 */
class ConcurrencyLimitReachedException extends RuntimeException
{
    public static function forLimit(int $limit): self
    {
        if ($limit <= 0) {
            return new self(
                'Delivery scheduling is disabled by the configured concurrency limit.',
            );
        }

        return new self(sprintf(
            'Delivery cannot be scheduled: the maximum of %d concurrent active deliveries has been reached.',
            $limit,
        ));
    }
}
