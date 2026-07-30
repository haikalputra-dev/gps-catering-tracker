<?php

declare(strict_types=1);

namespace App\Domain\Tracking;

use App\Domain\Delivery\DeliveryStatus;
use App\Models\Delivery;

/**
 * Customer-facing tracking authenticator (Packet 10).
 *
 * Given a raw receipt number and phone-last-four submission, resolves
 * the matching non-draft delivery if and only if both values line up
 * with the delivery's frozen snapshot data. All negative outcomes
 * (bad format, unknown receipt, phone mismatch, draft delivery) are
 * collapsed into a single `null` return so callers cannot distinguish
 * the failure modes and therefore cannot leak information about
 * receipt existence.
 *
 * The service is stateless, never throws for invalid input, never
 * touches the session, and never logs the submitted values.
 * Comparison of the last four phone digits uses `hash_equals` for
 * constant-time semantics (AR-42 revised).
 */
final class TrackingAuthenticator
{
    /**
     * Regex describing the canonical receipt-number shape as generated
     * by `ReceiptNumberGenerator` in Packet 07: `DEL-YYYYMMDD-XXXX`
     * where `XXXX` is 4 alphanumerics drawn from a 30-character
     * unambiguous alphabet.
     */
    private const RECEIPT_PATTERN = '/^DEL-\d{8}-[A-Z0-9]{4}$/';

    /**
     * Attempt to authenticate a tracking request.
     *
     * @param  string  $receiptNumber   Raw, user-provided receipt number.
     * @param  string  $phoneLastFour   Raw, user-provided phone-last-4.
     * @return Delivery|null            The matched delivery on success,
     *                                  or null on any failure mode.
     */
    public function attempt(string $receiptNumber, string $phoneLastFour): ?Delivery
    {
        $normalizedReceipt = $this->normalizeReceipt($receiptNumber);
        if ($normalizedReceipt === null) {
            return null;
        }

        $normalizedDigits = $this->normalizePhoneLastFour($phoneLastFour);
        if ($normalizedDigits === null) {
            return null;
        }

        // Only non-draft deliveries are trackable (AR-44). A draft has
        // no receipt in practice, so the whereIn also guards against
        // any hypothetical race in which the caller has learned a
        // draft's future receipt.
        $delivery = Delivery::query()
            ->where('receipt_number', $normalizedReceipt)
            ->whereIn('status', [
                DeliveryStatus::Scheduled->value,
                DeliveryStatus::InTransit->value,
                DeliveryStatus::Delivered->value,
                DeliveryStatus::Cancelled->value,
            ])
            ->first();

        if ($delivery === null) {
            return null;
        }

        $snapshotPhone = (string) ($delivery->customer_phone ?? '');
        $snapshotDigits = preg_replace('/\D/', '', $snapshotPhone) ?? '';
        $snapshotLastFour = substr($snapshotDigits, -4);

        if (strlen($snapshotLastFour) !== 4) {
            // A trackable delivery must carry a snapshot phone. If it
            // does not, treat the row as unauthenticatable rather than
            // throwing; the domain invariant is enforced at scheduling.
            return null;
        }

        if (! hash_equals($snapshotLastFour, $normalizedDigits)) {
            return null;
        }

        return $delivery;
    }

    /**
     * Normalize the receipt number into the canonical shape.
     *
     * Accepts hyphenated ("DEL-20260730-A7B3"), un-hyphenated
     * ("DEL20260730A7B3"), and case-insensitive input. Any other
     * shape yields null.
     */
    private function normalizeReceipt(string $raw): ?string
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        // Strip all non-alphanumeric characters and uppercase.
        $stripped = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $trimmed));

        // If the stripped form is exactly 15 chars (DEL + 8 digits +
        // 4 suffix), re-insert the hyphens and validate against the
        // canonical regex. Otherwise, fall back to uppercasing the
        // trimmed input and validate directly - the regex will reject
        // anything that doesn't already match the canonical shape.
        if (strlen($stripped) === 15) {
            $candidate = substr($stripped, 0, 3)
                .'-'.substr($stripped, 3, 8)
                .'-'.substr($stripped, 11, 4);
        } else {
            $candidate = strtoupper($trimmed);
        }

        return preg_match(self::RECEIPT_PATTERN, $candidate) === 1
            ? $candidate
            : null;
    }

    /**
     * Normalize the phone-last-four value.
     *
     * Must be exactly four digit characters after trimming. Any other
     * shape (letters, wrong length, empty) yields null.
     */
    private function normalizePhoneLastFour(string $raw): ?string
    {
        $trimmed = trim($raw);

        return preg_match('/^\d{4}$/', $trimmed) === 1
            ? $trimmed
            : null;
    }
}
