<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Exceptions;

use RuntimeException;

/**
 * Thrown when the caller attempts to cancel a delivery that is already
 * terminal (`delivered` or `cancelled`).
 */
class NotCancellableStateException extends RuntimeException
{
    public static function forStatus(string $current): self
    {
        return new self(sprintf(
            'Delivery cannot be cancelled from status "%s"; it is already terminal.',
            $current,
        ));
    }
}
