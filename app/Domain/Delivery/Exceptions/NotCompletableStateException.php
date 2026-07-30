<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Exceptions;

use RuntimeException;

/**
 * Thrown when the completer is invoked against a delivery whose status
 * is not `in_transit` (AR-41). Only the `in_transit → delivered` edge
 * is completable.
 */
class NotCompletableStateException extends RuntimeException
{
    public static function forStatus(string $status): self
    {
        return new self(sprintf(
            'Delivery cannot be marked delivered from its current status (%s).',
            $status,
        ));
    }
}
