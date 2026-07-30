<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Exceptions;

use RuntimeException;

/**
 * Thrown when a draft references an inactive kitchen at the moment of
 * scheduling. Inactive kitchens must not appear on newly scheduled deliveries.
 */
class InactiveKitchenException extends RuntimeException
{
    public static function forKitchenId(int $kitchenId): self
    {
        return new self(sprintf(
            'Delivery cannot be scheduled: kitchen #%d is inactive.',
            $kitchenId,
        ));
    }
}
