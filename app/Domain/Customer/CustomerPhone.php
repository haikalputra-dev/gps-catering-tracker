<?php

declare(strict_types=1);

namespace App\Domain\Customer;

use InvalidArgumentException;

/**
 * Customer phone normalizer.
 *
 * Trims whitespace and strips separators, preserving an optional single
 * leading `+`. Accepts practical Indonesian formats ("+62..." or "0...")
 * and rejects any alphabetic characters. Normalized output must contain
 * 9 to 15 digits.
 */
final class CustomerPhone
{
    public const MIN_DIGITS = 9;

    public const MAX_DIGITS = 15;

    /**
     * Normalize raw input into the canonical stored form.
     *
     * Behavior:
     *  - Non-string input returns the empty string.
     *  - Whitespace, hyphens and parentheses are removed.
     *  - A single leading `+` is preserved; further `+` characters are
     *    removed.
     */
    public static function normalize(mixed $raw): string
    {
        if (! is_string($raw)) {
            return '';
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return '';
        }

        $hasLeadingPlus = str_starts_with($trimmed, '+');

        // Strip everything except digits.
        $digits = preg_replace('/[^0-9]/', '', $trimmed) ?? '';

        return $hasLeadingPlus ? '+'.$digits : $digits;
    }

    /**
     * True when a value normalizes to an acceptable stored form.
     */
    public static function isValid(mixed $raw): bool
    {
        if (! is_string($raw)) {
            return false;
        }

        // Any alphabetic character in the raw input disqualifies the value.
        if (preg_match('/[A-Za-z]/', $raw) === 1) {
            return false;
        }

        $normalized = self::normalize($raw);
        if ($normalized === '') {
            return false;
        }

        $digitsOnly = ltrim($normalized, '+');
        $length = strlen($digitsOnly);

        if ($length < self::MIN_DIGITS || $length > self::MAX_DIGITS) {
            return false;
        }

        // Reject stray `+` in the middle: after normalization only a single
        // leading `+` may appear, everything else must be a digit.
        return preg_match('/^\+?[0-9]+$/', $normalized) === 1;
    }

    /**
     * Normalize and validate in one step.
     *
     * @throws InvalidArgumentException when the value is not a valid phone.
     */
    public static function fromInput(mixed $raw): string
    {
        if (! self::isValid($raw)) {
            throw new InvalidArgumentException('Invalid customer phone.');
        }

        return self::normalize($raw);
    }
}
