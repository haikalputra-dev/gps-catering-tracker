# ADR-021: Telemetry Simulator Command

- Status: Accepted
- Date: 2026-07-30
- Task Packet: 12
- Related: ADR-017 (device authentication),
  ADR-018 (telemetry rate limiting and retention),
  ADR-019 (device assignment and discard behaviour),
  ADR-020 (live-map polling and latest-telemetry endpoints),
  AR-54

## Context

The live map added by Packet 12 renders whatever `telemetry_records`
contains for the delivery being viewed. To exercise that surface
during development and QA — before ESP32 firmware exists — we needed
a deterministic way to produce a stream of realistic pings.

The Project Manager considered and rejected the following:

- **Database-write fixture.** Insert rows into `telemetry_records`
  directly, bypassing the HTTP path. Rejected: this leaves the entire
  ingestion pipeline (Bearer authentication, per-device rate limit,
  the AR-51 accept-and-discard rule, the AR-50 device-to-courier
  binding, the `last_seen_at` bump) untested by the fixture. A green
  live-map with a broken ingester would ship silently.
- **Persistent daemon.** Rejected: the simulator is a QA aid, not a
  production process. A one-shot Artisan command that a developer runs
  in a terminal keeps the surface small.
- **Randomised paths.** Rejected: the point of the simulator is to be
  predictable enough to compare screenshots and to sanity-check the
  markers. Real driving telemetry will not be deterministic; that's
  the firmware's problem, not the simulator's.
- **Explicit `--delivery` argument.** Rejected: the simulator should
  resolve the delivery the same way the ingester does (device ->
  assignment -> courier -> active delivery), so that the same
  invariants Packet 11 shipped are also enforced against the fake
  handset. A `--delivery` flag would let developers point the fake
  handset at deliveries that the real handset would never authenticate
  against.
- **A `--kitchen` / `--customer` coordinate override.** Rejected for
  the same reason: the coordinates should come from the delivery's
  scheduled snapshot columns so the simulator can never invent a route
  that a real dispatch would not.

## Decision

`php artisan telemetry:simulate` is an ESP32 stand-in. It POSTs to the
real `/api/telemetry` endpoint on the configured base URL, using the
device's real Bearer token in the `Authorization` header. It writes
nothing to the database directly.

### Options

| Option | Default | Meaning |
| --- | --- | --- |
| `--device=IDENT` | *required* | Matches `devices.identifier`. |
| `--interval=N` | `config('telemetry.simulator_default_interval_seconds')` | Seconds between pings; must be `>= 1`. |
| `--duration=N` | `config('telemetry.simulator_default_duration_seconds')` | Total run duration in seconds; must be `>= --interval`. |
| `--jitter=M` | `config('telemetry.simulator_default_jitter_meters')` | Uniform +/- M metres added to each ping; must be `>= 0`. |
| `--dry-run` | off | Compute the plan and print each tick, but issue no HTTP calls. |

### Resolution rules

1. **Device.** `Device::where('identifier', $ident)->first()`. Missing
   or inactive devices exit with a clear error and a non-zero status.
2. **Assignment.** The single open `device_assignments` row for the
   device (per AR-50). Absence exits with an error.
3. **Delivery.** The bound courier's most-recently-created `scheduled`
   or `in_transit` delivery. Absence exits with an error.

These are the same three lookups the ingester performs on every
request. The simulator's precondition failures therefore mirror the
runtime discard cases exactly.

### Path

The simulator walks a straight line between the delivery's
`kitchen_latitude` / `kitchen_longitude` and
`customer_latitude` / `customer_longitude` snapshot columns. The path
is discretised into `duration / interval` ticks; tick 0 is the kitchen
endpoint, tick N-1 is the customer endpoint, and intermediate ticks
are linear interpolations. The `--jitter` option adds a small uniform
offset to each ping so the marker on the live map does not sit
perfectly still on a single interpolated point.

For each ping the simulator computes:

- `latitude`, `longitude`: from the interpolator plus jitter.
- `speed_kmh`: `total_meters / duration * 3.6`, constant across the
  run. This is a plausible average, not the instantaneous derivative;
  the point is to have a value in the payload, not to model physics.
- `heading_degrees`: forward azimuth from the start point to the end
  point, constant across the run.
- `gps_timestamp`: server clock at tick time, formatted UTC
  `Y-m-d\TH:i:s\Z`.

### Transport

Each ping is a `POST` to
`config('telemetry.simulator_base_url') . '/api/telemetry'` via the
Laravel HTTP client, with `Bearer <device.api_token>`. A `204 No
Content` counts as accepted; `429 Too Many Requests` counts as
throttled; anything else counts as errored. All three counters are
printed at the end. Transport-level exceptions (connection refused,
timeout) print an error line and count as errored, not throttled.

### Signals

The command installs `SIGINT` and `SIGTERM` handlers on platforms
where the `pcntl` extension is available, so Ctrl-C during a long run
halts after the current tick. On platforms without `pcntl` (Windows,
minimal PHP builds) the command still runs but respects only the
`--duration` cap.

## Consequences

### Positive

- The simulator exercises the full ingest path: authentication,
  validation, rate limiting, the AR-50 binding lookup, the AR-51
  discard rule, and the `last_seen_at` bump. A green simulator run
  implies a working ingester.
- Because the path comes from the delivery's own snapshot columns, the
  simulator cannot produce a route that a real dispatch would not have
  produced. It also means the simulator's output is deterministic
  (modulo `--jitter`) — the same delivery always produces the same
  path.
- The command has no database write path and no shortcut to
  `TelemetryRecord::create()`. If ingestion is broken the simulator
  fails loudly; the two paths cannot diverge.

### Negative

- The simulator requires a running HTTP listener (`php artisan serve`
  or an nginx/Apache stack). A developer expecting to run the
  simulator against `sqlite :memory:` in a test terminal without a
  listener will see connection errors. This is by design: the
  simulator is not a fixture.
- The average-speed constant is a coarse approximation of real
  driving. Any consumer of `speed_kmh` that assumes it is the
  derivative of position will be wrong; consumers should treat it as a
  best-effort scalar in Packet 12.
- Because the simulator is time-bound (`sleep($interval)` between
  ticks), an interactive test that wants to see many pings quickly
  must use a small `--interval`. The 429 branch in the tests uses a
  faked HTTP client to avoid a real sleep-heavy run.

## Compliance surface

- **AR-54:** the simulator is an Artisan command that POSTs to the
  real `/api/telemetry` endpoint using the device's real Bearer
  token; it does not write to `telemetry_records` directly and it
  respects the same authentication, rate limit, and ingestion
  invariants as ESP32 firmware.
- **AR-58 (scope):** the simulator ships in Packet 12 as a QA aid;
  firmware code is explicitly out of scope.

## Alternatives considered

Enumerated under Context above. All were rejected before
implementation. The command's `handle()` and `simulate()` methods are
short enough that alternative path shapes (curved routes, multi-leg
runs) could be added later without changing the transport or
resolution rules.
