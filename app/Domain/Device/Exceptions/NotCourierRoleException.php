<?php

declare(strict_types=1);

namespace App\Domain\Device\Exceptions;

use RuntimeException;

/**
 * Raised when an assignment operation targets a User whose role is
 * not Courier (AR-50). Only couriers may be bound to a device.
 */
class NotCourierRoleException extends RuntimeException
{
    public static function forUserId(int $userId): self
    {
        return new self(sprintf(
            'User %d is not a courier and cannot be assigned to a device.',
            $userId,
        ));
    }
}
