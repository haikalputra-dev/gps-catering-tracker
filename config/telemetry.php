<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Telemetry retention window (AR-48)
    |--------------------------------------------------------------------------
    |
    | Number of days that a `telemetry_records` row is retained after it is
    | received. Packet 11 stores the value and applies it downstream; a
    | dedicated retention worker to purge older rows is deferred to a later
    | packet. The schema and configuration accommodate the worker without
    | further change.
    |
    | Values <= 0 are treated as "retain indefinitely" for the prototype;
    | production deployments should keep a positive integer here.
    |
    */

    'retention_days' => (int) env('TELEMETRY_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Per-device submission rate limit (AR-49)
    |--------------------------------------------------------------------------
    |
    | Maximum number of `POST /api/telemetry` submissions permitted per
    | authenticated device per one-minute rolling window. Enforced by the
    | named Laravel rate limiter `telemetry` (see bootstrap/app.php),
    | keyed on the resolved device id (`device:{id}`) rather than the
    | client IP so shared cellular gateways do not throttle unrelated
    | devices against one another.
    |
    | Values <= 0 are treated as "no submissions accepted" and will cause
    | every POST to receive a 429 response.
    |
    */

    'max_submissions_per_minute' => (int) env('TELEMETRY_MAX_SUBMISSIONS_PER_MINUTE', 60),

    /*
    |--------------------------------------------------------------------------
    | Bearer token alphabet and length (AR-47 revised)
    |--------------------------------------------------------------------------
    |
    | Device API tokens are drawn from `token_alphabet` with a length of
    | `token_length` characters. Generation uses `random_bytes` and
    | modulo-mapping into the alphabet. Both values are configurable so
    | the alphabet or length may be adjusted without touching domain code.
    |
    | The plaintext token is stored in `devices.api_token` (prototype
    | trade-off, AR-47 revised) and compared on ingestion with
    | `hash_equals` against the presented Bearer token.
    |
    */

    'token_length' => (int) env('TELEMETRY_TOKEN_LENGTH', 40),

    'token_alphabet' => (string) env(
        'TELEMETRY_TOKEN_ALPHABET',
        'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',
    ),

    /*
    |--------------------------------------------------------------------------
    | Live-map polling (AR-55, AR-57)
    |--------------------------------------------------------------------------
    |
    | `polling_interval_ms` is the default browser polling cadence for the
    | live-map JSON endpoints (`GET /deliveries/{delivery}/telemetry/latest`
    | and `GET /track/telemetry/latest`). The value is emitted as a
    | `data-interval` attribute on the map container; individual pages may
    | override it there without touching the config.
    |
    | `polling_max_per_minute` is the throttle cap applied to both endpoints
    | (`throttle:60,1`). Keep this aligned with the interval: a 3000 ms
    | interval implies at most 20 polls/minute per client, so the 60/min cap
    | leaves generous headroom for a small handful of concurrent viewers.
    |
    */

    'polling_interval_ms' => (int) env('TELEMETRY_POLLING_INTERVAL_MS', 3000),

    'polling_max_per_minute' => (int) env('TELEMETRY_POLLING_MAX_PER_MINUTE', 60),

    /*
    |--------------------------------------------------------------------------
    | Telemetry simulator (AR-54)
    |--------------------------------------------------------------------------
    |
    | `simulator_base_url` is the origin used by `telemetry:simulate` when
    | POSTing to `/api/telemetry`. The command exercises the real ingestion
    | pipeline (authentication, rate limit, ingester); it is not a direct
    | database write. Local development typically uses `http://127.0.0.1:8000`
    | matching `php artisan serve`.
    |
    | `simulator_default_interval_seconds`, `simulator_default_duration_seconds`,
    | and `simulator_default_jitter_meters` are the defaults for the matching
    | command-line options. All three may be overridden at invocation time
    | with `--interval`, `--duration`, and `--jitter`.
    |
    */

    'simulator_base_url' => rtrim(
        (string) env('TELEMETRY_SIMULATOR_BASE_URL', 'http://127.0.0.1:8000'),
        '/',
    ),

    'simulator_default_interval_seconds' => (int) env('TELEMETRY_SIMULATOR_INTERVAL', 2),

    'simulator_default_duration_seconds' => (int) env('TELEMETRY_SIMULATOR_DURATION', 120),

    'simulator_default_jitter_meters' => (float) env('TELEMETRY_SIMULATOR_JITTER', 0.0),

];
