<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Exceptions;

use RuntimeException;

/**
 * Thrown when a draft is submitted for scheduling with a `courier_id`
 * that points to a user whose role is not `courier` (AR-37).
 */
class CourierNotCourierRoleException extends RuntimeException
{
    public static function forCourierId(int $courierId): self
    {
        return new self(sprintf(
            'Delivery cannot be scheduled: user #%d is not a courier.',
            $courierId,
        ));
    }
}
