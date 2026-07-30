# ADR-015: Dispatch and Completion via Manual Courier Taps

- Status: Accepted
- Date: 2026-07-30
- Task Packet: 09
- Related: ADR-010 (delivery state machine),
  ADR-011 (delivery snapshots and receipt),
  ADR-014 (courier assignment),
  AR-35, AR-38 (revised), AR-39, AR-41

## Context

The state machine from ADR-010 declared but did not implement two
transitions:

- `scheduled → in_transit` — the moment the food physically leaves
  the kitchen.
- `in_transit → delivered` — the moment the food is handed to the
  customer.

Both events happen in the field, not in the office. Something has to
fire them, and we had to choose between two philosophies:

1. **Auto-detection.** Use GPS proximity to the kitchen (to fire
   dispatch) and to the customer (to fire delivery), or use device
   telemetry, or use SMS/push confirmation, or use customer taps.
2. **Manual courier taps.** The courier physically taps a button on
   their dashboard when they leave the kitchen and again when they
   hand over the package.

Auto-detection is more magical and shows well in demos. Manual taps
are more boring but survive rain, dead batteries, poor GPS lock, and
customers who don't have the app installed. AR-35 chose manual.

We also had to decide on a `failed` state (delivery attempted but
rejected by the customer) and on what timestamps to persist.

## Decision

### Manual, courier-initiated transitions

Two endpoints on the delivery resource:

- `POST /deliveries/{delivery}/dispatch` — invoked by the assigned
  courier when they leave the kitchen. Runs
  `DeliveryDispatcher::dispatch()`.
- `POST /deliveries/{delivery}/mark-delivered` — invoked by the
  assigned courier at hand-off. Runs `DeliveryCompleter::complete()`.

Both endpoints sit behind `role:courier` middleware. Both domain
services additionally assert that the acting user is the assigned
courier (matches `delivery.courier_id`) and that the courier is
still active. Both check the source state (`scheduled` for dispatch,
`in_transit` for complete) and raise a typed exception on violation.

There is no auto-detection, no GPS proximity trigger, no customer
confirmation, no SMS relay. If the courier does not tap, the
delivery does not transition. The office UI shows the current state
so staff can chase up stuck deliveries by phone.

### Two new timestamp columns

- `dispatched_at` (nullable `timestamp`): stamped by the dispatcher
  at the exact moment the state flips from `scheduled` to
  `in_transit`. Written as a UTC string via `Carbon::now('UTC')`.
- `delivered_at` (nullable `timestamp`): stamped by the completer at
  the flip from `in_transit` to `delivered`, also UTC.

Both fields are strictly monotonic within a single delivery row:
`dispatched_at <= delivered_at` (verified by
`DeliveryCompleterTest::test_delivered_at_is_at_or_after_dispatched_at`).
The completer never rewrites `dispatched_at`; the dispatcher never
touches `delivered_at`.

Both fields survive cancellation as-is. A cancelled row that had
previously been `in_transit` retains its `dispatched_at` for
after-the-fact auditing.

### No `failed` state

AR-39 rejected a `failed` terminal state. If a delivery cannot be
handed over, the courier or office cancels it (AR-38 revised
permits cancellation from `in_transit`). The cancellation reason
field is the audit trail. Adding a distinct `failed` state would
have doubled the terminal-state count and forced UI, filter, and
reporting changes without a clear operational payoff for the
prototype.

### Snapshot invariance

Firing dispatch or complete MUST NOT modify:

- `kitchen_name`, `kitchen_address`, `kitchen_lat`, `kitchen_lng`
- `customer_name`, `customer_address`, `customer_lat`, `customer_lng`
- `receipt_number`
- `distance_km`, `fee_rupiah`

These are frozen at scheduling time (ADR-011, ADR-013). The
dispatcher and completer only write status and timestamp columns.
This invariant is asserted directly by
`DeliveryDispatcherTest::test_dispatch_does_not_mutate_snapshot` and
its completer counterpart.

## Consequences

### Positive

- The state machine is fully implemented; no more "declared but
  unreachable" states.
- Field workflow is predictable and testable: two taps, two
  timestamps, no ambiguity.
- No dependency on device GPS accuracy, cellular data availability,
  or third-party SMS gateways.
- The role split is enforceable at the routing layer
  (`role:courier`) with per-actor identity re-check inside the
  domain service.

### Negative

- Requires trained couriers who remember to tap. A late tap means a
  late `delivered_at` timestamp. Acceptable for the prototype;
  auto-detection can be layered on in a future packet.
- No customer-side confirmation of delivery. The courier's tap is
  the source of truth. If disputes arise, the operator uses the
  frozen snapshot and the timestamps as the evidence trail.

## Alternatives considered

- **GPS proximity trigger.** Rejected for the prototype. Requires
  device provisioning, battery management, indoor/outdoor GPS
  quality handling, and privacy conversations. Explicitly excluded
  by AR-41.
- **Customer SMS confirmation.** Rejected. Adds a third-party
  dependency, cost per SMS, and unreliable delivery windows.
- **`failed` terminal state.** Rejected (AR-39). Cancellation with
  a reason covers the same audit need without doubling terminal
  cardinality.
- **Reassignment on courier no-show.** Rejected (AR-36). Cancel and
  re-draft is the supported workflow.
