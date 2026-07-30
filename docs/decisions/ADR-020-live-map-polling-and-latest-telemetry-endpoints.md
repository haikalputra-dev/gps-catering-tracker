# ADR-020: Live Map Polling and Latest-Telemetry Endpoints

- Status: Accepted
- Date: 2026-07-30
- Task Packet: 12
- Related: ADR-003 (free mapping stack),
  ADR-018 (telemetry rate limiting and retention),
  ADR-019 (device assignment and discard behaviour),
  AR-55, AR-56, AR-57, AR-58

## Context

Packet 12 delivers the visible half of the GPS feature: the live map.
Two questions had to be settled before any code was written.

1. **How does the browser learn about new courier positions?** The
   ingest side (Packet 11) already accepts pings from the handset at
   whatever cadence the firmware chooses. The question here is how the
   internal delivery-show view and the public tracking view learn about
   those positions.

2. **What does the customer-facing response include?** The staff surface
   needs enough detail to debug ("was the courier moving? which
   direction?"). The customer needs only enough to see the marker move.

The Project Manager considered and rejected the following:

- **WebSockets or SSE.** Rejected as overkill for the prototype. Both
  add a persistent connection, a background worker or broadcaster, and
  a second failure mode that the customer surface would inherit.
- **Long polling.** Rejected because the client-side timing story is
  identical to short polling for this cadence (seconds, not
  milliseconds), and long polling has worse browser-tab semantics.
- **A single unified endpoint for staff and customer.** Rejected
  because it would either leak `speed_kmh`, `heading_degrees`, and
  `gps_timestamp` to customers, or would gate them behind a role check
  that the customer surface cannot cleanly express (customers are not
  Laravel users; they authenticate via a tracking session).
- **A public-writable endpoint keyed on the receipt number.** Rejected
  as an attribution risk. Session-scoping the customer endpoint on
  `tracking.delivery_id` matches the rest of the tracking surface
  (ADR-016) and inherits the same throttle limits.
- **Configurable per-user cadence.** Rejected as a moving target for
  the throttle budget. A single default configured server-side keeps
  the 60/min ceiling comfortable at any active-viewer count.

## Decision

### Polling (AR-55)

The browser polls a JSON endpoint on a fixed interval using
`setInterval`. The default cadence is 3000 ms, configured by
`config('telemetry.polling_interval_ms')` and overridable per-page via
a `data-interval` attribute on the map container. The client stops the
interval when the page tab becomes hidden (`visibilitychange`) and
resumes it when the tab returns to the foreground; this is a courtesy
to the throttle bucket, not a security control.

There is no WebSocket, SSE, long-polling, or push channel introduced
by this packet. The two polling endpoints share the same throttle
bucket signature (`throttle:60,1` — 60 requests per minute per session
/ per user), which is generous relative to a 3 s cadence (20 req/min)
and leaves headroom for a customer refreshing the tab or a staff user
polling from multiple browser windows.

### Two endpoints (AR-57)

- `GET /deliveries/{delivery}/telemetry/latest`
  - Middleware: `web`, `auth`, `active`, `role:owner,staff,courier`,
    `throttle:60,1`.
  - Ownership: couriers are additionally restricted to their own
    delivery inside the controller (`403` on mismatch), because the
    `role:courier` middleware alone cannot express per-row scope. Owner
    and staff can poll any delivery.
  - Response body: `{delivery_id, status, latest}`. `latest` is either
    `null` (no records exist) or an object with `latitude`,
    `longitude`, `speed_kmh`, `heading_degrees`, `gps_timestamp`,
    `received_at`. `gps_timestamp` and `received_at` are formatted as
    UTC `Y-m-d\TH:i:s\Z`; the local Asia/Jakarta cast never leaves the
    server.

- `GET /track/telemetry/latest`
  - Middleware: `web`, `throttle:60,1`. No `auth` — the tracking flow
    is not backed by a Laravel user (ADR-016).
  - Session scope: the endpoint reads
    `session(TrackingController::SESSION_KEY)` and returns JSON `401`
    when the key is missing or points to a delivery that no longer
    exists. This matches the rest of the tracking surface and lets the
    JS client interpret non-2xx as "hold the last-known marker".
  - Response body: `{delivery_id, status, latest}`. `latest` is
    `null` unless the delivery is currently `in_transit`, regardless
    of whether telemetry rows exist. When present, `latest` contains
    only `latitude`, `longitude`, `received_at` — no `speed_kmh`, no
    `heading_degrees`, no `gps_timestamp`.

Both endpoints resolve the "latest" row by ordering
`telemetry_records` on the delivery by `received_at DESC` and taking
the first. `received_at` is the server clock at ingest, not
`gps_timestamp`, so client-side clock drift on the handset never
produces out-of-order rows.

### Shared JS module (AR-55, AR-56)

`resources/js/live-map.js` is a single 290-line module that auto-mounts
on any element carrying `data-live-map`. It reads `data-endpoint`,
`data-interval`, and the kitchen and customer snapshot coordinates
from the container's `data-*` attributes, initialises a Leaflet map
against the OSM tile URL configured in `config/map.php`, and then
starts the polling loop. Kitchen and customer markers are fixed; the
courier marker is a single moving icon. There is no breadcrumb trail,
no route line, and no historical playback UI. Non-2xx responses leave
the last-known marker in place and update a status paragraph so the
user knows the map is stale.

### Blade wiring (AR-58)

The delivery-show template renders the live-map card only when the
delivery status is `scheduled` or `in_transit`; the tracking-status
template renders the card only when the delivery status is
`in_transit`. In both cases the card is additive: no existing markup
is removed or restructured. The status gates match the endpoint
projection rules exactly, so the client never issues a poll that the
server would answer with `latest: null`.

## Consequences

### Positive

- Zero new npm dependencies (Leaflet 1.9.4 from Packet 03 is reused);
  no new server-side channel; no runtime worker.
- The customer-facing surface never receives `speed_kmh`,
  `heading_degrees`, or `gps_timestamp`. This is enforced by the
  provider, not by client-side filtering, so a leak requires an actual
  code change to `LatestTelemetryProvider::forCustomer()`.
- The two endpoints share a single throttle bucket signature but are
  keyed by session so a staff user cannot exhaust a customer's budget
  and vice versa.
- The JS module is agnostic to whether it is running on the staff or
  customer page. Both surfaces receive the same `latest` shape
  (`latitude`, `longitude`, `received_at`), so the module can consume
  either endpoint without a branch.

### Negative

- The 3 s cadence means a customer sees the marker jump in discrete
  steps rather than glide. This is deliberate — smoothing would need
  either a client-side animation or a much faster polling rate, and
  both were rejected in AR-55.
- The polling model reveals the *cadence* to network observers even
  when the delivery is idle. A future refinement could exponentially
  back off on repeated `latest: null` responses; Packet 12 does not
  attempt this because the throttle already caps the ceiling.
- The `visibilitychange` pause is a courtesy, not a defense: a browser
  extension or a headless client could sidestep it. The throttle
  bucket is the actual defense.

## Compliance surface

- **AR-55:** vanilla `setInterval`, 3000 ms default, `data-interval`
  override, no WebSocket / SSE / long-polling / push.
- **AR-56:** single moving courier marker, no breadcrumb, no route
  line, no historical playback.
- **AR-57:** two throttled polling endpoints; staff surface includes
  `speed_kmh` / `heading_degrees` / `gps_timestamp`; customer surface
  returns only `latitude` / `longitude` / `received_at` and `latest:
  null` unless `in_transit`.
- **AR-58:** additive Blade edits only; no schema change; no new npm
  dependency.

## Alternatives considered

Enumerated under Context above. All were rejected before
implementation. The polling loop is small enough that a future
transport swap (SSE, WebSockets) could replace the JS body without
changing the endpoint contract.
