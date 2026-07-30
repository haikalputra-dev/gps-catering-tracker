<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum concurrent active deliveries
    |--------------------------------------------------------------------------
    |
    | The number of simultaneously non-terminal deliveries permitted across the
    | whole system. "Non-terminal" means any delivery whose status is one of
    | `draft`, `scheduled` or `in_transit`. Terminal statuses (`delivered` and
    | `cancelled`) are excluded from the count.
    |
    | This is a configurable domain rule, NOT a database constraint (AR-27).
    | The MVP default of 1 matches the AR-05 single-active-delivery prototype
    | scope; later packets can raise it without a schema migration.
    |
    | Values <= 0 are treated as "no scheduling allowed" and will cause the
    | scheduler to reject every `draft → scheduled` transition.
    |
    */

    'max_concurrent_active' => (int) env('DELIVERY_MAX_CONCURRENT_ACTIVE', 1),

    /*
    |--------------------------------------------------------------------------
    | Receipt number format (AR-24)
    |--------------------------------------------------------------------------
    |
    | Receipt numbers use the pattern `<PREFIX>-YYYYMMDD-XXXX` where
    |   - PREFIX is a short, uppercase, ASCII identifier;
    |   - YYYYMMDD is the scheduling date in `receipt_date_timezone`;
    |   - XXXX is a random suffix of length `receipt_random_length` drawn
    |     from `receipt_random_alphabet` using `random_int`.
    |
    | The default alphabet intentionally excludes visually ambiguous glyphs
    | (`0`, `O`, `1`, `I`, `L`) so a receipt number can be reliably read
    | back over a phone call.
    |
    | Receipt numbers are immutable once assigned and must be unique across
    | the `deliveries` table. The generator retries up to 10 times on
    | collision before throwing.
    |
    */

    'receipt_prefix' => (string) env('DELIVERY_RECEIPT_PREFIX', 'DEL'),

    'receipt_random_length' => (int) env('DELIVERY_RECEIPT_RANDOM_LENGTH', 4),

    'receipt_random_alphabet' => (string) env(
        'DELIVERY_RECEIPT_RANDOM_ALPHABET',
        'ABCDEFGHJKMNPQRSTUVWXYZ23456789'
    ),

    /*
    |--------------------------------------------------------------------------
    | Receipt date timezone (AR-13, AR-28)
    |--------------------------------------------------------------------------
    |
    | `scheduled_at` is persisted in UTC (AR-28). The YYYYMMDD segment of a
    | receipt number is derived by converting the scheduling timestamp into
    | this timezone. The default matches AR-13 (Asia/Jakarta).
    |
    */

    'receipt_date_timezone' => (string) env('DELIVERY_RECEIPT_DATE_TIMEZONE', 'Asia/Jakarta'),

];
