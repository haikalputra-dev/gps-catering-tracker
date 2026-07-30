<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Exceptions;

use RuntimeException;

/**
 * Thrown when the caller attempts to schedule a delivery that is not currently
 * in `draft`. Non-draft deliveries carry immutable snapshots and must not be
 * mutated in place.
 */
class NotSchedulableStateException extends RuntimeException
{
    public static function forStatus(string $current): self
    {
        return new self(sprintf(
            'Delivery cannot be scheduled from status "%s"; only "draft" is allowed.',
            $current,
        ));
    }
}
