<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Exceptions;

use RuntimeException;

/**
 * Thrown when the dispatcher is invoked against a delivery whose status
 * is not `scheduled` (AR-41). Only the `scheduled → in_transit` edge is
 * dispatchable.
 */
class NotDispatchableStateException extends RuntimeException
{
    public static function forStatus(string $status): self
    {
        return new self(sprintf(
            'Delivery cannot be dispatched from its current status (%s).',
            $status,
        ));
    }
}
