<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Exceptions;

use RuntimeException;

/**
 * Thrown when a draft references an inactive courier at the moment of
 * scheduling, or when the assigned courier becomes inactive before
 * dispatch/completion (defensive check in the dispatcher/completer).
 */
class InactiveCourierException extends RuntimeException
{
    public static function forCourierId(int $courierId): self
    {
        return new self(sprintf(
            'Delivery cannot proceed: courier #%d is inactive.',
            $courierId,
        ));
    }
}
