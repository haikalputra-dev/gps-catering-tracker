# ADR-018: Telemetry Rate Limiting and Retention

- Status: Accepted
- Date: 2026-07-30
- Task Packet: 11
- Related: ADR-017 (device authentication),
  AR-48, AR-49

## Context

Once devices can authenticate, the ingest endpoint becomes an
attractive target for both abuse and honest-but-broken firmware.
A courier handset with a stuck retry loop can flood the server;
a leaked token can be replayed at machine speed. Neither scenario
should be able to fill the database or exhaust the web tier.

Two orthogonal quantities have to be pinned down:

1. **How often may a single device submit?**
2. **How long do accepted rows live?**

The alternatives considered were:

- **No rate limit.** Rejected: an infinite-loop firmware bug could
  fill the `telemetry_records` table before anyone notices.
- **IP-based route throttle (`throttle:60,1`).** Rejected: courier
  fleets often share carrier-grade NAT egress IPs, so an IP-keyed
  limiter degrades to a shared bucket in production. The
  authenticated device is the correct principal.
- **Per-user throttle keyed on the courier account.** Rejected:
  the caller has no `User`; the middleware only has the `Device`.
  Introducing a virtual user record would leak the design.
- **Application-level token bucket in the domain service.**
  Rejected: reinventing rate limiting when Laravel's named
  limiter framework already ships with the exact keying semantics
  required.
- **Permanent retention of telemetry.** Rejected as a hard
  no-go: a full year of 60-per-minute pings across a modest fleet
  produces multi-million-row tables with no operational value
  beyond a few weeks of history.
- **Aggressive retention (24 hours).** Rejected: some deliveries
  reasonably span more than 24 hours in dispute windows; the
  Project Manager wanted enough history to reconstruct a route
  after the delivery is closed.

## Decision

### Named rate limiter, per-device keying (AR-49)

`bootstrap/app.php` registers a named limiter called `telemetry`
inside `withRouting(then: ...)`. The callback:

- Reads the resolved `Device` from `$request->attributes->get('device')`.
- Builds the limiter key as `device:{id}` when a device is present,
  falling back to `ip:{ip}` when it is not. The fallback is a
  defense-in-depth path: in normal operation `AuthenticateDeviceToken`
  will have run first and either attached a device or already
  returned 401.
- Reads the cap from `config('telemetry.max_submissions_per_minute')`
  (default 60, environment override
  `TELEMETRY_MAX_SUBMISSIONS_PER_MINUTE`).
- Returns `Limit::perMinute(max(1, $perMinute))->by($key)`. The
  `max(1, ...)` floor keeps a misconfigured environment from
  producing a `perMinute(0)` value, which would let every request
  through with the current Laravel implementation.

The route registers as
`->middleware(['device.auth', 'throttle:telemetry'])`. In
declaration order this is correct, but Laravel's middleware
priority list normally hoists `ThrottleRequests` above every
non-prioritized middleware. `bootstrap/app.php` therefore also
calls `$middleware->prependToPriorityList(ThrottleRequests::class,
AuthenticateDeviceToken::class)` so device resolution always runs
first at runtime. Without that call, the limiter callback would
see a null `device` attribute and silently key on IP.

The regression test
`TelemetryRateLimitTest::test_rate_limit_is_scoped_per_device`
uses two devices behind identical client conditions and asserts
that one device exhausting its quota does not affect the other.
It fails deterministically if the priority declaration is
removed.

### 60 submissions per minute default (AR-49)

The Project Manager ratified 60/minute as the default cap. This
matches the intuition that a courier device pings roughly once
per second while in transit; anything above 60/minute in a
rolling window is either a firmware retry storm or a replay
attack. The value is a configuration knob rather than a hard
constant so operators can lower it under load without a code
change.

Exceeding the cap yields a `429 Too Many Requests` JSON response
carrying the framework's default `Retry-After` header. The
device is expected to back off and retry.

### 30-day retention default (AR-48)

`config('telemetry.retention_days')` defaults to 30
(environment override `TELEMETRY_RETENTION_DAYS`). Value ≤ 0
is documented as "retain indefinitely" so a debug environment
can accumulate every ping. Positive values represent the age
threshold beyond which a row is eligible for purge.

**A purge worker is not shipped in Packet 11.** The Project
Manager explicitly deferred the purge command to a later packet
so the ingest surface could land isolated. The schema and
configuration already accommodate it (`received_at` is the
canonical age column), and the operational contract in this ADR
is what a future purge worker must respect.

Retention applies only to `telemetry_records`. `devices` and
`device_assignments` are audit-first tables and are never
purged; a device that has not pinged in years still shows up in
the admin index with `last_seen_at` carrying the historical
value.

### Configuration surface

`config/telemetry.php` centralises all three tunables:

- `retention_days`
- `max_submissions_per_minute`
- `token_length`, `token_alphabet` (ADR-017 territory but co-located
  so the whole telemetry surface has one config file)

Environment variables are documented in `.env.example` under a
"Telemetry" section. Domain code reads only from `config(...)`,
never from `env(...)` directly, so cached configuration keeps
working in production.

## Consequences

### Positive

- The rate limit key isolates devices from each other regardless
  of shared egress IPs, which reflects the actual attribution unit
  in the operational model.
- Retention is a policy knob, not a magic constant. Tightening it
  during an incident is a one-line env change.
- The middleware-priority pin is asserted by an automated test, so
  a future refactor cannot silently regress AR-49.

### Negative

- The named limiter uses the framework cache store. In the
  `array` cache store (used by tests) the limiter resets between
  requests unless the same test kernel is reused, and in
  production a cache flush during an incident briefly resets
  every device's counter. Both are acceptable operational
  characteristics for a prototype.
- No purge worker means storage grows monotonically until Packet
  12 lands the retention job. Operators should watch table size
  in the interim.
- A malicious device with a valid token can burn its 60/minute
  quota indefinitely; there is no per-account or global ceiling.
  Adding one is straightforward (a second named limiter or a
  wildcard key) but was not required by AR-49.

## Compliance surface

- **AR-48:** 30-day default retention configured; purge worker
  deferred; retention scope limited to `telemetry_records`.
- **AR-49:** 60/minute default per authenticated device;
  device-keyed limiter registered as a named limiter; explicit
  middleware priority pin; regression test in place.

## Alternatives considered

Enumerated under Context above. The design intentionally keeps
the limiter and retention configuration co-located so future
adjustments (per-fleet quotas, dynamic retention by delivery
status) can extend the same `config/telemetry.php` surface
rather than sprawling across the codebase.
