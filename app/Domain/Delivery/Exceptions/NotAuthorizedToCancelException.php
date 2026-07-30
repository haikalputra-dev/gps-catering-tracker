<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Exceptions;

use RuntimeException;

/**
 * Thrown when a caller is not authorised to cancel this specific
 * delivery from its current status (AR-38 revised). Owner and staff
 * may cancel any non-terminal delivery; couriers may only cancel
 * their own `in_transit` delivery. All other combinations are
 * rejected with this exception.
 */
class NotAuthorizedToCancelException extends RuntimeException
{
    public static function forActor(int $actorId): self
    {
        return new self(sprintf(
            'User #%d is not authorised to cancel this delivery.',
            $actorId,
        ));
    }
}
