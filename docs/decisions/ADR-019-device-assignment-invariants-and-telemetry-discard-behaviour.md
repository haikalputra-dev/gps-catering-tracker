# ADR-019: Device Assignment Invariants and Telemetry Discard Behaviour

- Status: Accepted
- Date: 2026-07-30
- Task Packet: 11
- Related: ADR-017 (device authentication),
  ADR-018 (rate limiting and retention),
  AR-50, AR-51

## Context

Two related questions had to be answered before ingestion could ship:

1. **What is the binding model between a physical device and the
   courier who carries it?** Devices and couriers change hands
   independently — a device can be reassigned to a new courier if
   the old one leaves, and a courier who lost a device can be
   handed a replacement. The system needs a first-class record of
   which courier held which device at which moment, so that a
   telemetry row from six weeks ago can be attributed to a real
   person even after the current owner of the device has changed.

2. **What should happen when a device pings while no delivery is
   in progress?** A handset that stays powered on between deliveries
   will keep submitting pings. Rejecting those with an error status
   would encourage devices to disable telemetry entirely; accepting
   and storing them would fill the database with rows that carry no
   business meaning because they cannot be attributed to a
   `Delivery`.

The Project Manager considered and rejected the following:

- **Store courier_id directly on `devices`.** Rejected: overwrites
  history on every reassignment. A future ADR would have to add an
  audit table, and the delete-history-on-reassign behaviour would
  already have shipped by then.
- **Store the current binding on `users.device_id`.** Same problem
  inverted; same rejection.
- **Allow one courier to hold multiple devices.** Rejected: no
  operational scenario in the current fleet has a courier carrying
  two active handsets, and permitting it complicates the "current
  device for this delivery" lookup that Packet 12 needs.
- **Allow one device to be assigned to multiple couriers
  simultaneously.** Rejected as physically nonsensical: a device is
  a single piece of hardware.
- **Reject idle telemetry with 400 or 404.** Rejected: encourages
  devices to stop pinging between deliveries, which loses the
  ability to distinguish a firmware crash from an idle period.
- **Store idle pings against a synthetic "idle" delivery.**
  Rejected: pollutes the `deliveries` table with rows that will
  never appear on any tracking page, and complicates every
  delivery-scoped report.

## Decision

### 1:1 device-to-courier binding with history (AR-50)

`device_assignments` records the full binding history. The columns
are:

- `id`
- `device_id`
- `courier_id`
- `assigned_at` (server clock at assignment)
- `unassigned_at` (nullable; NULL indicates the current binding)
- `assigned_by_user_id`, `unassigned_by_user_id` (audit)
- `notes` (free-form, e.g. reason for reassignment)
- standard timestamps

The two invariants enforced by `DeviceAssignmentService` are:

- **At most one open row per device.** When a new assignment is
  requested and the device already has an open row for a *different*
  courier, the existing row is closed with `unassigned_at = now()`
  before the new row is inserted. Assigning the same courier to the
  same device that they already hold is a no-op that returns the
  existing row.
- **At most one open row per courier across all devices.** If the
  target courier is already bound to a different device,
  `assign()` throws `CourierAlreadyBoundException`. The UI catches
  this and surfaces a validation error on the `courier_id` field.

Both invariants are asserted inside a `DB::transaction` with
`lockForUpdate()` on the target rows so concurrent assign requests
serialize instead of double-binding. `unassign()` is idempotent:
calling it on a device with no open row returns `null` and does
nothing.

Deactivating a device via `PUT /devices/{device}` (setting
`is_active = 0`) auto-closes the open assignment with the note
"device deactivated". This is the recovery path when a handset
is lost: the owner deactivates the device in the admin UI, the
courier's binding is released, and the courier can be assigned a
replacement device immediately without a manual unassign step.

Devices are never deleted (AR-50). The disable path is a state
flag on the row.

### Attribution rule for telemetry

A `telemetry_records` row references two ids: the `device_id`
that authenticated the submission, and the `delivery_id` whose
courier held the device at submission time. The delivery is
resolved by:

1. Reading `device.currentAssignment.courier_id` (the sole open
   row on `device_assignments` for the device).
2. If the courier has one or more `Delivery` rows in
   `scheduled` or `in_transit` status (per
   `Delivery::activeForCourier` scope), pick the most recently
   dispatched one. Ties break by descending `id`.

The tie-break rule is defensive. Packet 09's concurrency cap
already ensures a courier holds at most one active delivery, so
in practice the "most recent" and "only" cases coincide. Tests
can construct edge cases with more than one active delivery to
verify the tie-break, without those cases being reachable
through the production UI.

### Accept-and-discard when no context (AR-51)

The ingester returns `null` (which the controller translates to
`204 No Content`) in three cases, all of which are considered
*accepted but discarded*:

- The device has no open assignment (no courier is currently
  carrying this handset).
- The bound courier has no `scheduled` or `in_transit` delivery.
- Any transient reason the ingester might extend later — the
  return contract is "record or null", not "record or throw".

No `telemetry_records` row is created in any of these cases.

However, `devices.last_seen_at` is bumped on every *accepted*
call, including discarded ones. This is the fundamental signal
that the device is alive on the network. An admin who ships a
handset out and never sees `last_seen_at` update knows the
device never authenticated or never powered up; that diagnostic
must not disappear during idle periods.

Validation failures (`422`) and auth failures (`401`) do *not*
bump `last_seen_at`, because they never reach the ingester.

### Delivery status semantics

Only `scheduled` and `in_transit` deliveries are considered
"active" for the purposes of attribution. `delivered` and
`cancelled` are terminal (per ADR-010) and ADR-015's
delivery-cancellation refinement. Pings that arrive after a
delivery is marked delivered or cancelled fall through to the
discard branch — the device stays alive, but the row that
would have attributed the ping no longer exists.

## Consequences

### Positive

- The binding audit is complete: any historical telemetry row
  can be traced back to the courier who held the device at that
  moment, even after the device has been reassigned multiple
  times.
- The discard-by-default policy keeps `telemetry_records`
  meaningful — every stored row corresponds to a real courier
  and a real delivery — while never punishing an idle device
  with a client-visible error.
- `last_seen_at` gives operators a single freshness column on
  the device index without any dependency on delivery state.

### Negative

- The ingest path performs two extra queries even on discarded
  submissions (assignment lookup, delivery lookup). At high
  throughput this is measurable; it is not measured or optimized
  in Packet 11 because the target scale is small.
- A courier can be handed a new handset even mid-delivery. The
  new device has no history of pings for that delivery until
  the reassignment is complete, and no synthetic "handoff" row
  is inserted. Consumers of the tracking data must be robust to
  gaps.
- The tie-break on multiple active deliveries is defensive
  rather than authoritative. If Packet 12 or later relaxes the
  concurrency cap, the resolution rule may need to be revisited.

## Compliance surface

- **AR-50:** 1:1 device-to-courier binding, reassignment history,
  deactivate-on-lost path, devices never deleted.
- **AR-51:** accept-and-discard when no active delivery,
  `last_seen_at` bump on every accepted call, 204 for both
  persisted and discarded outcomes.

## Alternatives considered

Enumerated under Context above. All were rejected before
implementation. The `DeviceAssignmentService` and
`TelemetryIngester` are both small enough that alternative
binding rules or delivery-resolution strategies could be added
without changing the controller or middleware surfaces.
