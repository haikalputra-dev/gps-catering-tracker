<?php

declare(strict_types=1);

namespace App\Domain\Kitchen;

use InvalidArgumentException;

/**
 * Normalizes kitchen codes to a canonical uppercase form.
 *
 * Rules:
 *  - trim surrounding whitespace
 *  - convert letters to uppercase
 *  - only A-Z, 0-9 and hyphen are permitted in the final form
 *  - internal spaces, underscores and other punctuation are rejected
 *  - maximum 30 characters
 */
final class KitchenCode
{
    public const MAX_LENGTH = 30;

    /** @var non-empty-string */
    private const PATTERN = '/^[A-Z0-9-]{1,30}$/';

    /**
     * Normalize the input into the canonical stored form.
     *
     * Trims and uppercases; does not silently strip disallowed characters,
     * so downstream validation can reject them.
     */
    public static function normalize(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        return strtoupper(trim($value));
    }

    /**
     * Whether the (already-normalized) value is a valid stored code.
     */
    public static function isValid(string $normalized): bool
    {
        return preg_match(self::PATTERN, $normalized) === 1;
    }

    /**
     * Normalize and validate in one step. Throws on invalid input.
     */
    public static function fromInput(?string $value): string
    {
        $normalized = self::normalize($value);
        if (! self::isValid($normalized)) {
            throw new InvalidArgumentException(
                'Kitchen code must contain only uppercase letters, digits and hyphens (max 30).'
            );
        }

        return $normalized;
    }
}
