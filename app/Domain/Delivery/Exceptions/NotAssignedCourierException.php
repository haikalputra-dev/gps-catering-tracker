<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Exceptions;

use RuntimeException;

/**
 * Thrown when a courier attempts to dispatch, complete, or cancel a
 * delivery that is not assigned to them (AR-41). Only the assigned
 * courier may operate on a delivery; other couriers are rejected with
 * a domain-level 403-equivalent.
 */
class NotAssignedCourierException extends RuntimeException
{
    public static function forCourierId(int $courierId): self
    {
        return new self(sprintf(
            'User #%d is not the courier assigned to this delivery.',
            $courierId,
        ));
    }
}
