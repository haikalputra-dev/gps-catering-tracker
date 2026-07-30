<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Exceptions;

use RuntimeException;

/**
 * Thrown when a caller invokes {@see \App\Domain\Delivery\DeliveryCanceller}
 * without providing a non-empty cancellation reason of the required length.
 *
 * The FormRequest layer normally enforces this ahead of the service, so
 * hitting this exception at runtime indicates a bypass of the request layer.
 */
class CancellationReasonRequiredException extends RuntimeException
{
    public static function missing(): self
    {
        return new self(
            'Delivery cancellation requires a reason of 3 to 255 characters.',
        );
    }
}
