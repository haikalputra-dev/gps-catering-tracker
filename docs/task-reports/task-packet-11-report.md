# Task Packet 11 Report: Device Registration and Telemetry Ingestion

- **Packet**: 11
- **Slice**: Owner-only physical device roster with token issuance and
  reassignment history; a single authenticated `POST /api/telemetry`
  endpoint that ingests GPS pings, resolves the active delivery via
  the device's current courier binding, and enforces per-device rate
  limiting. No purge worker, no map, no read API.
- **Starting commit**: `318a187 feat: add customer receipt-tracking
  authentication and status page`
- **Branch**: `main`
- **Date completed**: 2026-07-30

## Scope summary

Packet 11 introduces the device-side of the tracking pipeline. Owners
register a physical handset in the admin UI, receive a plaintext
Bearer token, and bind the device 1:1 to a courier via the
`device_assignments` audit table. The device then posts GPS
telemetry to `POST /api/telemetry` with an `Authorization: Bearer`
header. The ingester attributes each ping to the active delivery of
the currently bound courier and drops pings that arrive when no
delivery is in progress. Every accepted call updates
`devices.last_seen_at` regardless of whether a row was persisted.

No changes were made to the delivery state machine, to any existing
admin surface, to `/home/ubuntu/GPS-server`, or to `.env`. The
delivery route surface remains unchanged; existing suites continue
to pass.

## Deliverables

### Governance

- `AR-47..AR-52` appended to `docs/project/decision-log.md`; header
  updated to `AR-01 through AR-52`.
- `AR-47` marked "Approved (revised)": plaintext `devices.api_token`
  storage with `hash_equals` comparison, resolving the earlier
  ambiguity between hashed and plaintext options for the prototype.
- ADR-017 (device authentication and API token lifecycle) records
  the token-shape, storage trade-off, uniform 401 contract, and
  rotation semantics.
- ADR-018 (telemetry rate limiting and retention) records the named
  limiter, per-device keying, middleware-priority pin, and the
  30-day retention window with purge-worker deferral.
- ADR-019 (device assignment invariants and telemetry discard
  behaviour) records the 1:1 binding rule, history model, and the
  accept-and-discard policy for idle pings.

### Schema

- `devices` migration: `label`, `serial_number` (both unique),
  `hardware_model`, `is_active`, `api_token` (unique), `last_seen_at`,
  timestamps.
- `device_assignments` migration: `device_id`, `courier_id`,
  `assigned_at`, `unassigned_at` (nullable), `assigned_by_user_id`,
  `unassigned_by_user_id`, `notes`, timestamps.
- `telemetry_records` migration: `device_id`, `delivery_id`,
  `courier_id`, `latitude`, `longitude`, `accuracy_m`, `speed_mps`,
  `heading_deg`, `battery_percent`, `gps_timestamp`, `received_at`,
  timestamps. `gps_timestamp` and `received_at` stored as UTC.

### Domain

- `App\Domain\Device\ApiTokenGenerator`: draws tokens from the
  configured alphabet using `random_bytes`, retries on collision.
- `App\Domain\Device\DeviceAssignmentService`: transactional 1:1
  binding with history; asserts courier role and active flags;
  auto-closes existing open rows on reassignment; idempotent on
  same-courier re-assign; throws `CourierAlreadyBoundException` for
  cross-device courier conflicts.
- `App\Domain\Device\TelemetryPayload`: value object carrying the
  validated ingest fields.
- `App\Domain\Device\TelemetryIngester`: normalizes `gps_timestamp`
  to UTC, resolves the delivery via
  `Delivery::activeForCourier`, discards when no delivery is
  active, persists a `telemetry_records` row otherwise, and always
  bumps `devices.last_seen_at`.

### HTTP

- `App\Http\Middleware\AuthenticateDeviceToken` (alias
  `device.auth`): parses the `Authorization: Bearer` header,
  matches with `hash_equals`, rejects inactive devices, attaches
  the resolved `Device` to `$request->attributes['device']`.
  Uniform `401 { "message": "Unauthorized." }` for every failure.
- `App\Http\Requests\Device\StoreDeviceRequest`,
  `UpdateDeviceRequest`, `AssignDeviceRequest`: field-level rules
  for label/serial uniqueness and courier role/active checks.
- `App\Http\Requests\Telemetry\TelemetryStoreRequest`: numeric
  ranges for lat/lng, ISO 8601 timestamp, optional fields with
  their own ranges. Returns Laravel's default `422` JSON shape on
  failure.
- `App\Http\Controllers\Device\DeviceController`: nine actions
  matching the route table (index, create, store, show, edit,
  update, rotateToken, assign, unassign). Uses
  `DeviceAssignmentService` for the two binding actions and flashes
  the plaintext token once on create and on rotate.
- `App\Http\Controllers\Telemetry\TelemetryController::store`:
  reads the device from request attributes, delegates to the
  ingester, returns `204 No Content` for both persisted and
  discarded outcomes.

### Routes

Nine device routes added under the authenticated `role:owner`
group (all under `devices.*`), plus one API route outside `web`:

- `GET /devices` -> `devices.index`
- `GET /devices/create` -> `devices.create`
- `POST /devices` -> `devices.store`
- `GET /devices/{device}` -> `devices.show`
- `GET /devices/{device}/edit` -> `devices.edit`
- `PUT /devices/{device}` -> `devices.update`
- `POST /devices/{device}/rotate-token` -> `devices.rotate-token`
- `POST /devices/{device}/assign` -> `devices.assign`
- `POST /devices/{device}/unassign` -> `devices.unassign`
- `POST /api/telemetry` (middleware `device.auth`, `throttle:telemetry`)
  -> `api.telemetry.store`

There is no `destroy` action for devices (AR-50) and no admin or
public read endpoint for telemetry (AR-52).

### Middleware and configuration

- `bootstrap/app.php`: registers the `device.auth` alias, registers
  the named `telemetry` limiter (`Limit::perMinute(cfg)->by('device:{id}')`
  with `ip:{ip}` fallback), and prepends `AuthenticateDeviceToken`
  above `ThrottleRequests` in the priority list so device
  resolution runs before the limiter.
- `config/telemetry.php`: `retention_days` (default 30),
  `max_submissions_per_minute` (default 60), `token_length`
  (default 40), `token_alphabet` (alphanumeric).
- `.env.example`: `TELEMETRY_RETENTION_DAYS`,
  `TELEMETRY_MAX_SUBMISSIONS_PER_MINUTE`, `TELEMETRY_TOKEN_LENGTH`,
  `TELEMETRY_TOKEN_ALPHABET`.

### Views

- `resources/views/devices/index.blade.php`: roster with label,
  serial, hardware model, active flag, current courier binding,
  `last_seen_at`, and quick links to show/edit.
- `resources/views/devices/create.blade.php`,
  `edit.blade.php`, `show.blade.php`: standard admin CRUD with
  the token flash on create and rotate, and an inline assign /
  unassign panel on show.

### Tests

Eight new test files, all passing:

| File | Tests | Focus |
| --- | --- | --- |
| `tests/Unit/Domain/Device/ApiTokenGeneratorTest.php` | 5 | Length matches config, alphabet restriction, uniqueness under repeated generation, retry on collision, configuration override |
| `tests/Unit/Domain/Device/DeviceAssignmentServiceTest.php` | 11 | Assign on empty device, reassign closes existing row, idempotent same-courier assign, cross-device courier conflict throws, unassign closes open row, unassign no-op when clean, inactive device rejected, inactive courier rejected, non-courier role rejected, actor recorded on both sides, deactivation auto-closes assignment |
| `tests/Unit/Domain/Device/TelemetryIngesterTest.php` | 8 | Happy path persists with attribution, discards when no assignment, discards when no active delivery, tie-break on multiple active deliveries, `last_seen_at` bumps on persist, `last_seen_at` bumps on discard, UTC normalization stored, `courier_id` mirrors the current assignment |
| `tests/Feature/Device/DeviceManagementTest.php` | 11 | Index/create/store/edit/update/show gated to owner, staff and courier 403, label uniqueness, serial uniqueness, plaintext token flashed once on create, rotate replaces token and flashes new value, deactivation auto-closes assignment, missing device 404 |
| `tests/Feature/Device/DeviceAssignmentTest.php` | 9 | Assign happy path, unassign happy path, form-request rejects non-courier role, form-request rejects inactive courier, cross-device courier conflict flagged, idempotent re-assign of same courier, actor recorded, unassign flashes success, unassign on already-clean device is a no-op |
| `tests/Feature/Telemetry/TelemetryAuthenticationTest.php` | 8 | Missing header 401, malformed header 401, unknown token 401, deactivated device 401, active device with valid token accepted, uniform response body across all failure modes, no session cookie set, no CSRF requirement |
| `tests/Feature/Telemetry/TelemetryIngestionTest.php` | 10 | Persisted row shape, `204` for persisted, `204` for discarded (no assignment), `204` for discarded (no active delivery), UTC normalization stored, `last_seen_at` bumped on persisted, `last_seen_at` bumped on discarded, `422` on missing lat/lng, `422` on out-of-range lat, `422` on invalid `gps_timestamp` |
| `tests/Feature/Telemetry/TelemetryRateLimitTest.php` | 4 | 60 accepted then 429, `429` retry-after present, per-device isolation (regression for middleware priority), fallback IP key when no device (defensive) |

### Documentation

- `docs/decisions/ADR-017-device-authentication-and-api-token-lifecycle.md`
- `docs/decisions/ADR-018-telemetry-rate-limiting-and-retention.md`
- `docs/decisions/ADR-019-device-assignment-invariants-and-telemetry-discard-behaviour.md`
- `docs/requirements/device-and-telemetry-requirements.md`
- `docs/project/decision-log.md`: `AR-47..AR-52` appended, header
  updated, Packet 11 governance-audit note recorded.
- `docs/project/change-log.md`: 2026-07-30 Packet 11 entry planned
  at the top of the log (Added / Behavior / Tests / Decisions).
- `docs/project/progress.md`: "Device registration, courier
  binding, telemetry ingestion (authenticated, rate-limited)" line
  added between the customer-tracking entry and the "Domain
  implementation" bucket.

## Verification

### Test suite

```
php artisan test
Tests: 440, Assertions: 1305, Duration: <baseline+>
Result: passed
```

Baseline before Packet 11 was 374 tests / 1107 assertions. Packet 11
added 66 tests (24 unit + 42 feature) and 198 assertions. A final
suite run is queued after the decision-log delta pass.

### Static audits

- `composer audit`: no new advisories.
- `npm audit --production`: no vulnerabilities.

### Frontend build

- `npm run build`: succeeds. No new JS or CSS was introduced for
  device or telemetry surfaces; the six new Blade views reuse the
  authenticated app layout with inline styles matching the existing
  admin idiom.

### Route surface

`php artisan route:list --path=devices` reports exactly nine
routes under `devices.*`. `php artisan route:list --path=api`
reports exactly one route (`api.telemetry.store`). The delivery
route surface is unchanged.

## Decisions applied

- **AR-47 (revised)**: plaintext token storage with `hash_equals`
  comparison; uniform `401 { "message": "Unauthorized." }` across
  all authentication failure modes.
- **AR-48**: 30-day default retention on `telemetry_records`; purge
  worker deferred; `devices` and `device_assignments` never purged.
- **AR-49**: named `telemetry` rate limiter, 60 requests per minute
  per authenticated device, keyed on `device:{id}`; middleware
  priority pinned so authentication runs before throttling.
- **AR-50**: 1:1 device-to-courier binding recorded in an
  append-only `device_assignments` audit table; devices are never
  deleted; deactivation auto-releases the courier.
- **AR-51**: accept-and-discard when no active delivery exists;
  `devices.last_seen_at` bumped on every accepted call regardless
  of persistence.
- **AR-52**: combined scope Bearer authentication with `204`/`401`/
  `422`/`429` response contract; no telemetry read surface; delivery
  admin surface unchanged.

## Out of scope (Packet 11)

- Retention purge worker (deferred; schema and config in place).
- Live map, WebSocket, SSE, or Pusher push channel for telemetry.
- Per-device history page or JSON export.
- Batched telemetry submission or `POST` payload arrays.
- Hashed or encrypted token storage.
- Global or per-fleet rate limits beyond the per-device cap.
- Any change to the delivery state machine, columns, or existing
  admin surfaces.
- Any change to `/home/ubuntu/GPS-server`.
