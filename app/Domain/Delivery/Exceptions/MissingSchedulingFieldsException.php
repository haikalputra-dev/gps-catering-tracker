<?php

declare(strict_types=1);

namespace App\Domain\Delivery\Exceptions;

use RuntimeException;

/**
 * Thrown when a draft is missing the fields required to transition to
 * `scheduled` (kitchen, customer, or a future `scheduled_at`).
 */
class MissingSchedulingFieldsException extends RuntimeException
{
    public static function forField(string $field): self
    {
        return new self(sprintf(
            'Delivery cannot be scheduled: required field "%s" is missing or invalid.',
            $field,
        ));
    }
}
