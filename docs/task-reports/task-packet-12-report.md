# Task Packet 12 Report: Live-Position Tracking and Simulator

- **Packet**: 12
- **Slice**: Two JSON polling endpoints (staff/owner/courier and
  customer surfaces) that expose the latest telemetry row for a
  delivery; a shared Leaflet-based live-map module wired into the
  internal delivery-show and public tracking-status pages via
  additive Blade edits; a `telemetry:simulate` Artisan command that
  drives the map by POSTing to the real `/api/telemetry` endpoint
  as a real device. No schema change, no new npm dependency, no
  firmware code.
- **Starting commit**: `ed2dae5 feat: add device registration and
  telemetry ingestion pipeline (Packet 11)`
- **Branch**: `main`
- **Date completed**: 2026-07-30

## Scope summary

Packet 12 delivers the visible half of the GPS feature. Packet 11
already accepts pings and persists them; Packet 12 exposes them.

- Two endpoints — one for the internal roles (`owner`, `staff`,
  assigned `courier`), one for the customer tracking session — return
  the latest telemetry row for a delivery in a stable JSON envelope.
- A shared 290-line vanilla-JS module (`resources/js/live-map.js`)
  polls the appropriate endpoint on a 3 s default cadence, renders a
  Leaflet map with fixed kitchen and customer markers, and moves a
  single courier marker as new positions arrive.
- The delivery-show and tracking-status views gain a live-map card
  gated on the delivery's status (`scheduled`+`in_transit` for the
  internal view; `in_transit` only for the customer view).
- `php artisan telemetry:simulate` walks a straight-line path between
  the delivery's kitchen and customer snapshot coordinates, POSTing
  each interpolated point to `/api/telemetry` with the device's real
  Bearer token — exercising the full ingest path rather than
  short-circuiting to a DB write.

No schema migration, no npm dependency addition, no change to the
delivery state machine, no change to any existing admin surface, no
edits to `/home/ubuntu/GPS-server` or `.env`.

## Deliverables

### Governance

- `AR-53..AR-58` appended to `docs/project/decision-log.md`; header
  updated to `AR-01 through AR-58`.
- `AR-53` marked "Approved (retroactive)": ratifies the columns and
  naming Packet 11 shipped (`devices.model`,
  `devices.hardware_version`, `devices.notes`,
  `devices.last_seen_at`, `telemetry_records.speed_kmh`,
  `telemetry_records.heading_degrees`) without any code change. The
  Packet 12 governance-audit note explicitly records that no
  `battery_percent` or `accuracy_m` columns exist.
- ADR-020 (live-map polling and latest-telemetry endpoints) records
  the two-endpoint design, the staff/customer projection split, the
  session-scoped 401 contract for the customer endpoint, and the
  courier ownership guard on the internal endpoint.
- ADR-021 (telemetry simulator command) records the "real HTTP path,
  no DB shortcut" contract, the resolution rules that mirror the
  ingester, the linear-path interpolation model, and the signal
  handling behaviour.

### Endpoints

- `GET /deliveries/{delivery}/telemetry/latest`, name
  `deliveries.telemetry.latest`. Middleware: `web`, `auth`, `active`,
  `role:owner,staff,courier`, `throttle:60,1`. Couriers are
  additionally restricted to their own delivery inside the
  controller (`403` on mismatch). Full staff surface: `latitude`,
  `longitude`, `speed_kmh`, `heading_degrees`, `gps_timestamp`,
  `received_at`.
- `GET /track/telemetry/latest`, name `tracking.telemetry.latest`.
  Middleware: `web`, `throttle:60,1`. No `auth`; the endpoint reads
  `session(TrackingController::SESSION_KEY)` and returns JSON `401`
  when the session is missing or stale. Customer surface returns
  only `latitude`, `longitude`, `received_at`, and `latest: null`
  for any status other than `in_transit`.

### Domain / HTTP layer

- `app/Domain/Telemetry/LatestTelemetryProvider.php`: builds the
  staff and customer JSON envelopes, formats timestamps as UTC
  `Y-m-d\TH:i:s\Z` by reading raw values (never the Asia/Jakarta
  cast).
- `app/Http/Controllers/DeliveryTelemetryController.php`: thin
  controller; role membership is handled by route middleware,
  ownership by the guard inside `latest()`, shaping by the provider.
- `app/Http/Controllers/TrackingTelemetryController.php`:
  session-scoped; returns JSON `401` (not a redirect) on stale
  sessions so the JS client interprets non-2xx as "hold the marker".

### JavaScript

- `resources/js/live-map.js` (290 lines): auto-mounts on any element
  carrying `data-live-map`. Reads `data-endpoint`, `data-interval`,
  kitchen and customer coordinates, and tile-server config from
  `data-*` attributes. Handles 401 / 403 / 429 responses by leaving
  the last-known marker in place and updating a status paragraph.
  Stops on `visibilitychange` (tab hidden), resumes on return.
- Wired via `resources/js/app.js` (single new `import`).

### Blade + CSS

- `resources/views/deliveries/show.blade.php`: additive live-map
  card gated on `scheduled|in_transit`.
- `resources/views/tracking/status.blade.php`: additive live-map
  card gated on `in_transit` only.
- `resources/css/app.css`: `.live-map-container`, `.live-map-status`,
  `.live-map-dot` rules added; no existing rules touched.

### Simulator

- `app/Console/Commands/SimulateTelemetryCommand.php` (332 lines):
  registered as `telemetry:simulate`. Validates options, resolves
  device -> assignment -> active delivery, walks a linear path
  between kitchen and customer snapshot coordinates, and POSTs each
  tick to the real `/api/telemetry` endpoint via `Http::withToken`.
  `--dry-run` prints the plan without issuing HTTP requests.
  Installs SIGINT/SIGTERM handlers when `pcntl` is available.
  Private method renamed from `run()` to `simulate()` to avoid
  shadowing `Illuminate\Console\Command::run`.
- `app/Domain/Telemetry/PathInterpolator.php`: helpers for the
  simulator (`interpolate`, `bearing`, `distanceMeters`,
  `jitterOffsetDegrees`).

### Configuration

- `config/telemetry.php`: extended with `polling_interval_ms`,
  `polling_max_per_minute`, `simulator_base_url`,
  `simulator_default_interval_seconds`,
  `simulator_default_duration_seconds`,
  `simulator_default_jitter_meters`. All existing keys retained.
- `.env.example`: matching `TELEMETRY_POLLING_*` and
  `TELEMETRY_SIMULATOR_*` block appended.

### Tests (65 new)

- `tests/Unit/Domain/Telemetry/LatestTelemetryProviderTest.php`
  (6 tests): staff vs customer projection, most-recent-wins,
  `latest: null` on status gate.
- `tests/Unit/Domain/Telemetry/PathInterpolatorTest.php`
  (13 tests): interpolation, bearing, distance, jitter.
- `tests/Feature/Delivery/DeliveryTelemetryLatestTest.php`
  (10 tests): auth wiring, role gates, courier ownership,
  throttle 60/min.
- `tests/Feature/Tracking/TrackingTelemetryLatestTest.php`
  (11 tests): session scope, `in_transit` gate, projection rules,
  throttle 60/min.
- `tests/Feature/Telemetry/SimulateTelemetryCommandTest.php`
  (15 tests): option validation, resolution rules, dry-run behaviour,
  real HTTP behaviour under `Http::fake()`, payload shape,
  throttled-response handling.
- `tests/Feature/LiveMap/LiveMapRenderingTest.php`
  (10 tests): status-gate matrix on both surfaces, endpoint wiring,
  polling-interval propagation.

The pre-existing `DeliveryRouteTest::test_only_ten_delivery_routes_are_registered`
was updated to `test_only_the_expected_delivery_routes_are_registered`
so the closed route-name set now includes
`deliveries.telemetry.latest`.

## Verification

- `php artisan test`: **505 passed, 1600 assertions, 0 failures** in
  ~17 s (SQLite `:memory:`). Baseline was 440/1305; the 65 new tests
  plus the corrected route-set assertion account for the delta.
- `composer audit`: no vulnerabilities.
- `npm audit`: no vulnerabilities.
- `npm run build`: builds cleanly in 434 ms. Final bundle
  `app-*.js` = 157.09 kB (45.81 kB gzip), which includes the
  pre-existing Leaflet 1.9.4 dependency and the new
  `live-map.js` module. Leaflet marker assets emitted by the
  bundler are unchanged.

## Decisions that shipped

Every AR row for Packet 12 was implemented as written:

- **AR-53**: retroactive ratification; no code change.
- **AR-54**: `telemetry:simulate` hits the real
  `/api/telemetry` endpoint using the device's real Bearer token
  and does not write to `telemetry_records` directly.
- **AR-55**: vanilla `setInterval`, 3000 ms default,
  `data-interval` override on the container; no WebSocket / SSE /
  long-polling / push.
- **AR-56**: single moving courier marker, fixed kitchen and
  customer markers, no breadcrumb, no route line, no historical
  playback UI.
- **AR-57**: two throttled polling endpoints; staff surface
  includes `speed_kmh` / `heading_degrees` / `gps_timestamp`;
  customer surface returns only `latitude` / `longitude` /
  `received_at` and `latest: null` unless `in_transit`.
- **AR-58**: additive Blade edits only; no schema change; no new
  npm dependency; firmware, purge worker, breadcrumbs, route lines,
  historical playback, WebSockets, SSE, push notifications,
  geofencing, deviation alerts, multi-delivery view, and real-time
  customer notifications are all out of scope.

## Deliberate omissions

- **Retention purge worker.** Still deferred (per ADR-018). Schema
  and config remain in place; nothing in Packet 12 depends on it.
- **Firmware.** No ESP32 or GPS module code. The simulator stands
  in for the handset.
- **Fleet / multi-delivery map.** Each map instance is scoped to a
  single delivery; there is no owner-level map that shows every
  courier at once.
- **Breadcrumb / route line.** A single marker, no history trail,
  by AR-56.
- **Real-time push.** Polling only, by AR-55.
- **Customer speed / heading / GPS timestamp.** Never on the wire,
  by AR-57.
- **Adaptive cadence.** No exponential back-off on repeated
  `latest: null` responses; the throttle bucket is the ceiling.
- **Battery / accuracy telemetry.** No columns exist for these; the
  Packet-12 governance audit note ratifies their absence.

## Follow-ups for the next packet

- Consider a purge worker driven by
  `config('telemetry.retention_days')`. The infrastructure is in
  place; the missing pieces are the scheduled job and its
  observability.
- Consider an owner-only fleet map that lists all in-transit
  couriers on a single Leaflet canvas. The polling endpoints and
  the JS module could be extended without adding a new transport.
- Consider a courier-authenticated write for "problem reports"
  (e.g. "package delivered to a receptionist, not the named
  recipient"). This would need a new route and its own governance
  row.
- Consider surface-level polish on the live map: a "last update"
  age indicator, and a colour change on the courier marker when
  the response is `latest: null` for more than N consecutive polls.
