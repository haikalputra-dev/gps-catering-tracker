<?php

declare(strict_types=1);

namespace App\Domain\Device\Exceptions;

use RuntimeException;

/**
 * Raised when an assignment operation targets an inactive courier
 * (`is_active = false`). Only active couriers may be bound to a device.
 */
class InactiveCourierException extends RuntimeException
{
    public static function forUserId(int $userId): self
    {
        return new self(sprintf(
            'Courier %d is inactive and cannot be assigned to a device.',
            $userId,
        ));
    }
}
