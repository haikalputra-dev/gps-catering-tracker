# Receipt Numbers

Every delivery that reaches `scheduled` is issued a human-readable
receipt number. This document specifies the format, generation
algorithm, and immutability guarantees. See ADR-011 for the design
rationale.

## Format

`DEL-YYYYMMDD-XXXX`

Example: `DEL-20260730-K7QP`

- `DEL` is the prefix, configurable via `delivery.receipt_prefix`.
- `YYYYMMDD` is the calendar date of the scheduling operation in
  Asia/Jakarta. The receipt-date timezone is configurable via
  `delivery.receipt_date_timezone`.
- `XXXX` is a random suffix of length 4 (configurable via
  `delivery.receipt_random_length`) drawn from a 30-character alphabet
  (configurable via `delivery.receipt_random_alphabet`).

## Alphabet

The default alphabet is `ABCDEFGHJKMNPQRSTUVWXYZ23456789`. Removed
characters:

- `0` (looks like `O`)
- `O` (looks like `0`)
- `1` (looks like `I` and `L`)
- `I` (looks like `1`)
- `L` (looks like `1`)

The alphabet is intentionally uppercase-only for legibility on thermal
printers and phone screens.

## Generation

`App\Domain\Delivery\ReceiptNumberGenerator::generate(Carbon $scheduledAt)`:

1. Formats the date component in the configured timezone.
2. Picks each suffix character using `random_int()` for
   cryptographically strong randomness.
3. Composes the candidate string.
4. Verifies uniqueness via `SELECT ... FOR UPDATE` on
   `deliveries.receipt_number`.
5. Retries up to 10 times on collision.
6. Throws `RuntimeException` if all attempts collide.

The generator is stateless and injected into the scheduler; tests
exercise it directly.

## Uniqueness

The `deliveries.receipt_number` column is a `varchar(20)` unique index
that permits `NULL`. Only scheduled or cancelled-from-scheduled
deliveries have a receipt; drafts and drafts-cancelled remain `NULL`.
MySQL treats multiple `NULL` values as distinct under a unique index,
so this arrangement holds without a partial index.

## Immutability

Once generated, a receipt number is never rewritten. The scheduler is
the only writer; the canceller preserves the receipt on
`scheduled to cancelled`. Editing is disabled for non-draft
deliveries, so no admin path exists to mutate a receipt number.

## Configuration reference

`config/delivery.php`:

```php
return [
    'max_concurrent_active' => env('DELIVERY_MAX_CONCURRENT_ACTIVE', 1),
    'receipt_prefix' => env('DELIVERY_RECEIPT_PREFIX', 'DEL'),
    'receipt_random_length' => env('DELIVERY_RECEIPT_RANDOM_LENGTH', 4),
    'receipt_random_alphabet' => env(
        'DELIVERY_RECEIPT_RANDOM_ALPHABET',
        'ABCDEFGHJKMNPQRSTUVWXYZ23456789',
    ),
    'receipt_date_timezone' => env(
        'DELIVERY_RECEIPT_DATE_TIMEZONE',
        'Asia/Jakarta',
    ),
];
```

`.env.example` ships with the defaults.

## Collision probability

At default settings (30 alphabet, length 4) the suffix space is
`30 ^ 4 = 810,000` values per day. Even with 100 deliveries scheduled
in a single day the probability of any collision is well below one
percent. In practice the retry loop absorbs the rare collision without
operator visibility.

## Not in scope for Packet 07

- Public tracking page keyed by receipt number
- Receipt-number lookup authorization tokens
- QR code generation
- Reprint or reissue workflows
