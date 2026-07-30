<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Exceptions;

use RuntimeException;

/**
 * Thrown when a draft is submitted for scheduling without a `courier_id`
 * (AR-37) or when the referenced courier row cannot be located.
 */
class MissingCourierException extends RuntimeException
{
    public static function missing(): self
    {
        return new self('Delivery cannot be scheduled: an active courier must be assigned.');
    }

    public static function forCourierId(int $courierId): self
    {
        return new self(sprintf(
            'Delivery cannot be scheduled: courier #%d does not exist.',
            $courierId,
        ));
    }
}
