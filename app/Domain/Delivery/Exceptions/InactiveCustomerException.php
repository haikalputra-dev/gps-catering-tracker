<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Exceptions;

use RuntimeException;

/**
 * Thrown when a draft references an inactive customer at the moment of
 * scheduling. Inactive customers must not appear on newly scheduled deliveries.
 */
class InactiveCustomerException extends RuntimeException
{
    public static function forCustomerId(int $customerId): self
    {
        return new self(sprintf(
            'Delivery cannot be scheduled: customer #%d is inactive.',
            $customerId,
        ));
    }
}
