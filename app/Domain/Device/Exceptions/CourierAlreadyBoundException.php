<?php

declare(strict_types=1);

namespace App\Domain\Device\Exceptions;

use RuntimeException;

/**
 * Raised when an assignment operation targets a courier who already
 * holds an open assignment to a different device.
 *
 * The admin must unassign the courier from their current device
 * explicitly before binding them to a new one; the service refuses to
 * silently move a courier between devices so history is unambiguous.
 */
class CourierAlreadyBoundException extends RuntimeException
{
    public static function forCourierId(int $courierId, int $currentDeviceId): self
    {
        return new self(sprintf(
            'Courier %d is already bound to device %d; unassign first.',
            $courierId,
            $currentDeviceId,
        ));
    }
}
