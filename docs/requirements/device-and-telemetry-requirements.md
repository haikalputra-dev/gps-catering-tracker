# Device and Telemetry Requirements

Source-of-truth requirements for the device administration surface and
the authenticated telemetry ingestion API. Scope is Packet 11:
owner-only physical device CRUD, 1:1 device-to-courier binding with
full reassignment history, per-device Bearer-token authentication for
a single `POST /api/telemetry` endpoint, and per-device rate limiting.
No purge worker, no map, no real-time push, no telemetry read API.

## R-DEV-01: Device roster is owner-only

The device administration surface is served under the authenticated
`role:owner` middleware group. Staff and couriers cannot list, view,
create, edit, deactivate, rotate tokens for, or bind devices. The
nine device routes (`devices.index`, `create`, `store`, `show`,
`edit`, `update`, `rotate-token`, `assign`, `unassign`) all reject
non-owner sessions with the framework's `403` response.

## R-DEV-02: Device identity

Each `Device` row carries:

- `label` — free-form human-friendly name, unique per row, required.
- `serial_number` — hardware identifier, unique per row, required.
- `hardware_model` — free-form nullable description.
- `is_active` — boolean, defaults to true.
- `api_token` — plaintext Bearer secret (AR-47 revised).
- `last_seen_at` — nullable timestamp of the most recent accepted
  telemetry submission (AR-51).

Labels and serial numbers are trimmed and validated for uniqueness at
form-request level. Duplicates surface as field-level validation
errors, not as database constraint exceptions.

## R-DEV-03: Devices are never deleted

There is no `DELETE /devices/{device}` route and no soft-delete
column. The disable path is `is_active = false`, applied via the
standard `PUT /devices/{device}` update action. A deactivated device
whose token is presented against `POST /api/telemetry` receives
`401 Unauthorized` — the token remains valid data, but the device is
no longer accepted.

## R-DEV-04: Deactivation auto-releases assignment

Setting `is_active` to false via the update action closes any open
row on `device_assignments` for that device with a system-generated
`notes = 'device deactivated'` and `unassigned_at = now()`. The
courier who previously held the device becomes immediately eligible
for a new assignment.

## R-DEV-05: Token generation and rotation

Tokens are drawn by `ApiTokenGenerator` from
`config('telemetry.token_alphabet')` with length
`config('telemetry.token_length')` (default 40). Generation uses
`random_bytes` and modulo-mapping into the alphabet. A generated
token is retried if it collides with an existing `devices.api_token`
row so uniqueness is asserted at creation time as well as by the
schema.

The `POST /devices/{device}/rotate-token` action replaces the stored
token with a freshly generated one and flashes the new plaintext
value once. The previous token is immediately invalidated; any
in-flight request bearing it receives `401`.

## R-DEV-06: Token storage trade-off

`devices.api_token` is stored in plaintext (AR-47 revised). The
Project Manager accepted this trade-off for the prototype so token
comparison can use `hash_equals($stored, $presented)` without a
separate lookup index or hashing scheme. The choice is revisited if
device volume grows beyond prototype scale.

## R-DEV-07: 1:1 assignment with history

`device_assignments` is an append-only audit table:

- `device_id`, `courier_id`, `assigned_at`, `unassigned_at`
  (nullable — NULL means the current binding).
- `assigned_by_user_id`, `unassigned_by_user_id`.
- `notes`.

Invariants enforced by `DeviceAssignmentService`:

- At most one open row per device.
- At most one open row per courier across all devices.
- Assigning the same courier to the same device is idempotent.
- Assigning a device already open to a different courier closes the
  existing row before opening the new one.
- Assigning a courier who is already bound to a different device
  throws `CourierAlreadyBoundException`, which the UI renders as a
  `courier_id` field validation error.

All writes occur inside a `DB::transaction` with `lockForUpdate()`
on the target device and courier open-row queries.

## R-DEV-08: Assignment actor identity

`assigned_by_user_id` and `unassigned_by_user_id` are populated from
the authenticated session's `User` when the action runs through the
admin controllers. Deactivation-triggered unassignments carry the
acting owner's id on `unassigned_by_user_id`; system-triggered
unassignments without an actor context leave the column NULL.

## R-DEV-09: Assignment target constraints

`assign()` refuses to bind:

- an inactive device (throws domain exception);
- an inactive courier (throws domain exception);
- a user whose role is not `courier` (throws domain exception).

The Form Request also enforces `courier_id`'s existence, role, and
active flag before reaching the service, so the service exceptions
are defensive rather than user-facing in the common path.

## R-DEV-10: Ingestion endpoint

A single `POST /api/telemetry` route (name `api.telemetry.store`) is
registered. No other telemetry route exists. No `GET`, `PUT`, or
`DELETE` telemetry endpoint is registered. Requests to any other
verb receive `405 Method Not Allowed`.

## R-DEV-11: Bearer token authentication

The `device.auth` middleware (`AuthenticateDeviceToken`) enforces:

- An `Authorization: Bearer <token>` header must be present.
- The token must match a row in `devices` under `hash_equals`.
- The matched device must have `is_active = true`.

Every failure mode — missing header, malformed header, unknown
token, deactivated device — returns the same `401 Unauthorized`
JSON body:

```json
{ "message": "Unauthorized." }
```

No body variation, no header hint, no error code. Devices cannot
distinguish failure modes from observed behavior.

## R-DEV-12: Middleware order

The route chain declares `['device.auth', 'throttle:telemetry']`.
`bootstrap/app.php` calls
`prependToPriorityList(ThrottleRequests::class, AuthenticateDeviceToken::class)`
so device resolution runs before rate limiting at runtime. This is
enforced by
`TelemetryRateLimitTest::test_rate_limit_is_scoped_per_device`.

## R-DEV-13: Per-device rate limit

The named limiter `telemetry` (registered in `bootstrap/app.php`)
returns `Limit::perMinute(max(1, config('telemetry.max_submissions_per_minute')))
->by('device:' . $device->id)` when a device is present, and falls
back to `ip:{ip}` otherwise. The default cap is 60 per minute
(`TELEMETRY_MAX_SUBMISSIONS_PER_MINUTE`).

Exhaustion returns `429 Too Many Requests` with the framework's
default `Retry-After` header. One device's exhausted quota does not
affect another device's quota.

## R-DEV-14: Ingestion payload

`POST /api/telemetry` requires a JSON body with:

- `latitude` — numeric, in the range `-90.0` to `90.0` inclusive.
- `longitude` — numeric, in the range `-180.0` to `180.0` inclusive.
- `gps_timestamp` — RFC 3339 / ISO 8601 timestamp with timezone.
  Interpreted in UTC and stored as UTC on the row.
- `accuracy_m` — optional non-negative numeric (metres).
- `speed_mps` — optional non-negative numeric (metres per second).
- `heading_deg` — optional numeric in `[0, 360)`.
- `battery_percent` — optional integer in `[0, 100]`.

Validation failures return `422 Unprocessable Entity` with the
default Laravel JSON error shape. The device's `last_seen_at` is
**not** bumped on `422`.

## R-DEV-15: Attribution rule

The delivery attached to a stored telemetry row is resolved by:

1. Look up the sole open `device_assignments` row for the
   authenticating device.
2. Read `courier_id` from that row.
3. Find the courier's active `Delivery` rows (statuses `scheduled`
   or `in_transit`). If more than one exists, pick the most
   recently dispatched, breaking ties by descending `id`.

The row's `courier_id` mirrors the assignment's courier for audit
independence: a later reassignment leaves the historical row's
`courier_id` intact.

## R-DEV-16: Accept-and-discard

The ingester returns `null` (translated to `204 No Content` by the
controller) — and creates no `telemetry_records` row — when:

- The device has no open assignment.
- The bound courier has no `scheduled` or `in_transit` delivery.

`204` is the response for both persisted and discarded outcomes.
The device cannot distinguish the two from the response.

## R-DEV-17: last_seen_at semantics

`devices.last_seen_at` is set to `now()` on every submission that
reaches the ingester and is accepted (204), regardless of whether
a row was persisted. It is **not** updated on `401`, `422`, or
`429` because those requests never reach the ingester.

## R-DEV-18: UTC normalization

The ingester normalizes `gps_timestamp` to UTC before writing.
`TelemetryRecord::$casts['gps_timestamp']` is `datetime`, which
rehydrates naive DB strings in the application timezone; the
canonical stored value is UTC and is asserted via
`getRawOriginal('gps_timestamp')` in the ingester test.

## R-DEV-19: Retention window

`telemetry_records` rows are retained for
`config('telemetry.retention_days')` days (default 30) after the
value on `received_at`. A retention worker is not shipped in
Packet 11; the configuration and the `received_at` column exist
to support it in a later packet. `devices` and `device_assignments`
are never purged.

## R-DEV-20: No web session on the API path

`POST /api/telemetry` is served outside the `web` middleware group.
No CSRF token is required, no session is started, no `web`-group
cookie is read. Exceptions bubble as JSON because the request path
matches `api/*` in `bootstrap/app.php`.

## R-DEV-21: No telemetry read surface

Packet 11 introduces no admin or public read endpoint for
`telemetry_records`. There is no map, no list view, no JSON export,
and no per-device history page. Rows are written and never
displayed.

## R-DEV-22: Delivery state machine unchanged

Packet 11 introduces no columns on `deliveries`, no new statuses,
and no changes to any transition. The delivery routes and views
retain the state surface established in Packet 09 and refined in
ADR-015. Attribution reads delivery status but never writes it.

## Traceability

| Requirement | Approved row | Implementing artifact |
| --- | --- | --- |
| R-DEV-01 | AR-52 | `routes/web.php` device group |
| R-DEV-02 | AR-47 revised, AR-51 | `Device` migration and model |
| R-DEV-03 | AR-50 | Absence of destroy route; `is_active` flag |
| R-DEV-04 | AR-50 | `DeviceController::update`, `DeviceAssignmentService::unassign` |
| R-DEV-05 | AR-47 revised | `ApiTokenGenerator`, `DeviceController::rotateToken` |
| R-DEV-06 | AR-47 revised | `devices.api_token` column, `AuthenticateDeviceToken` |
| R-DEV-07 | AR-50 | `DeviceAssignmentService`, `device_assignments` migration |
| R-DEV-08 | AR-50 | `DeviceAssignmentService::assign/unassign` |
| R-DEV-09 | AR-50 | `DeviceAssignmentService::assertCourier` |
| R-DEV-10 | AR-52 | `routes/web.php` `/api/telemetry` |
| R-DEV-11 | AR-47 revised, AR-52 | `AuthenticateDeviceToken` |
| R-DEV-12 | AR-49, AR-52 | `bootstrap/app.php` priority list |
| R-DEV-13 | AR-49 | `bootstrap/app.php` named limiter |
| R-DEV-14 | AR-52 | `TelemetryStoreRequest` |
| R-DEV-15 | AR-50, AR-51 | `TelemetryIngester::resolveDelivery` |
| R-DEV-16 | AR-51 | `TelemetryIngester::ingest`, `TelemetryController::store` |
| R-DEV-17 | AR-51 | `TelemetryIngester::touchLastSeen` |
| R-DEV-18 | AR-52 | `TelemetryIngester`, `TelemetryRecord` cast |
| R-DEV-19 | AR-48 | `config/telemetry.php` `retention_days` |
| R-DEV-20 | AR-52 | Route outside `web` group; `bootstrap/app.php` `api/*` match |
| R-DEV-21 | AR-52 | Absence of any telemetry read route |
| R-DEV-22 | AR-52 | `routes/web.php` deliveries group unchanged |

## Out of scope (Packet 11)

- Retention purge worker (deferred; schema and config in place).
- Map or live-tracking UI backed by `telemetry_records`.
- Per-device history page or JSON export.
- Batched telemetry submission or `POST` payload arrays.
- Hashed or encrypted token storage.
- Global or per-fleet rate limits beyond the per-device cap.
- Push channel (WebSocket, SSE, Pusher) for telemetry.
- Any change to the delivery state machine, columns, or existing
  admin surfaces.
