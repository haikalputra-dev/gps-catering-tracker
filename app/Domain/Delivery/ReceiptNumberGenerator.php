<?php

declare(strict_types=1);

namespace App\Domain\Delivery;

use App\Models\Delivery;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use RuntimeException;

/**
 * Generates unique delivery receipt numbers of the form `PREFIX-YYYYMMDD-XXXX`.
 *
 * The date segment is derived from the scheduling timestamp converted to the
 * configured `delivery.receipt_date_timezone` (default `Asia/Jakarta`). The
 * random suffix is drawn from `delivery.receipt_random_alphabet` using
 * `random_int` and has `delivery.receipt_random_length` characters.
 *
 * The generator retries up to {@see MAX_ATTEMPTS} times on uniqueness
 * collision before throwing a {@see RuntimeException}. The alphabet size
 * (31^4 = 923,521 for the default) makes exhausting all attempts effectively
 * impossible in normal operation and indicates a serious problem worth
 * surfacing loudly.
 */
class ReceiptNumberGenerator
{
    /**
     * Maximum number of random-suffix attempts before we abort. Deliberately
     * matches the value quoted in AR-24.
     */
    public const MAX_ATTEMPTS = 10;

    /**
     * Generate a unique receipt number for a delivery scheduled at $scheduledAt.
     *
     * The caller MUST persist the returned string in `deliveries.receipt_number`
     * within the same transaction that captures the scheduling to avoid a race
     * against a concurrent scheduler.
     *
     * @throws RuntimeException When all attempts collide with existing receipts
     *                          or when configuration is invalid.
     */
    public function generate(DateTimeInterface $scheduledAt): string
    {
        $prefix = $this->configuredPrefix();
        $datePart = $this->formatDatePart($scheduledAt);
        $alphabet = $this->configuredAlphabet();
        $length = $this->configuredRandomLength();

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            $candidate = sprintf(
                '%s-%s-%s',
                $prefix,
                $datePart,
                $this->randomSuffix($alphabet, $length),
            );

            if (! $this->receiptExists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException(sprintf(
            'Failed to generate a unique delivery receipt number after %d attempts.',
            self::MAX_ATTEMPTS,
        ));
    }

    /**
     * True when a delivery already exists with the given receipt number.
     */
    private function receiptExists(string $receipt): bool
    {
        return Delivery::query()
            ->where('receipt_number', $receipt)
            ->exists();
    }

    private function formatDatePart(DateTimeInterface $scheduledAt): string
    {
        $timezoneName = (string) config('delivery.receipt_date_timezone', 'Asia/Jakarta');
        $timezone = new DateTimeZone($timezoneName);

        $utc = DateTimeImmutable::createFromInterface($scheduledAt);

        return $utc->setTimezone($timezone)->format('Ymd');
    }

    private function configuredPrefix(): string
    {
        $prefix = (string) config('delivery.receipt_prefix', 'DEL');

        if ($prefix === '') {
            throw new RuntimeException('Delivery receipt prefix must not be empty.');
        }

        return $prefix;
    }

    private function configuredRandomLength(): int
    {
        $length = (int) config('delivery.receipt_random_length', 4);

        if ($length < 1) {
            throw new RuntimeException('Delivery receipt random length must be >= 1.');
        }

        return $length;
    }

    private function configuredAlphabet(): string
    {
        $alphabet = (string) config(
            'delivery.receipt_random_alphabet',
            'ABCDEFGHJKMNPQRSTUVWXYZ23456789',
        );

        if ($alphabet === '') {
            throw new RuntimeException('Delivery receipt alphabet must not be empty.');
        }

        return $alphabet;
    }

    /**
     * Pull $length characters from $alphabet using `random_int`.
     */
    private function randomSuffix(string $alphabet, int $length): string
    {
        $max = strlen($alphabet) - 1;
        $out = '';

        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }

        return $out;
    }
}
