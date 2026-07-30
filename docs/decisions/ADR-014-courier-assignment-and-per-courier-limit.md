# ADR-014: Courier Assignment and Per-Courier Limit

- Status: Accepted
- Date: 2026-07-30
- Task Packet: 09
- Related: ADR-005 (prototype concurrency),
  ADR-010 (delivery state machine),
  ADR-012 (configurable concurrency),
  AR-34, AR-37, AR-41

## Context

Packet 07 introduced the delivery state machine (`draft → scheduled →
in_transit → delivered`), and Packet 08 added the frozen distance and
fee. Neither packet said anything about WHO physically transports the
food. In practice the catering owner has a small pool of couriers on
staff, and one of them must be attached to each delivery before it
leaves the kitchen.

Two independent decisions had to be made:

1. When in the lifecycle the courier is bound to a delivery.
2. Whether multiple deliveries can be assigned to the same courier at
   the same time (i.e. is the per-courier concurrency limit `1` or
   something greater).

We also had to decide whether couriers may be reassigned after
scheduling. The AR-36 decision closed that door: no reassignment.

## Decision

### Assignment timing

`courier_id` is a nullable foreign key on the `deliveries` table:

- **Draft**: `courier_id` MAY be null. Owners and staff often build a
  draft (customer, kitchen, notes) before knowing which courier will
  be free later, so requiring a courier at draft time would force an
  awkward "pick anyone, we'll change it later" step.
- **Scheduling**: `courier_id` MUST be set. The scheduler asserts a
  non-null courier before flipping the state to `scheduled` and
  before freezing the address snapshot / receipt / distance / fee.

This is codified in AR-37 and enforced by
`DeliveryScheduler::assertCourier()`.

### Per-courier concurrency limit

At scheduling time the scheduler counts how many deliveries the
target courier already has in an active state
(`draft`, `scheduled`, `in_transit`). If that count is at or above
`config('delivery.max_concurrent_per_courier')`, scheduling is refused
with `CourierConcurrencyLimitReachedException`.

The default is `1`, matching the operational reality of the
prototype: one active delivery per courier at a time. The value is
configurable via env for flexibility (AR-34) — a larger operation
running two-drop routes could raise it to `2` without a code change.

Setting the limit to `0` or negative blocks all courier assignment,
which is a supported operating mode for site-wide pauses.

### No reassignment

Once `courier_id` is set on a `scheduled`, `in_transit`, or terminal
delivery, it cannot be changed. AR-36 rejected reassignment for the
prototype scope: it interacts poorly with the frozen fee (the fee was
priced against a specific courier's expected route) and with
`dispatched_at` (which courier physically left the kitchen?).

If a courier becomes unavailable mid-route, the correct workflow is
to cancel the delivery (owner/staff may cancel from any non-terminal
state, AR-38 revised) and create a fresh draft for a different
courier.

### Inactive-courier protection

At scheduling AND at dispatch/complete time, the code re-checks
`courier->is_active`. A courier who was active at draft time but
deactivated before dispatch cannot fire the state transitions on
their side. This closes a narrow but real race between office
deactivation and courier action.

## Consequences

### Positive

- The office can build drafts asynchronously without knowing the
  final courier.
- The per-courier limit prevents accidental double-booking of a
  single physical person.
- Configurable limit gives the operator a knob for future scale
  without a schema change.
- Inactive-courier protection means deactivating a courier is a
  first-class "you can't work right now" gate, not just a login
  toggle.

### Negative

- Two nullable states (`courier_id = null` on draft, non-null on
  scheduled+) create a small branching in queries. The `active`
  scope on `Delivery` handles this transparently.
- No reassignment means a courier who calls in sick forces a cancel
  + re-draft cycle. That is acceptable for the prototype.

## Alternatives considered

- **Require courier at draft time.** Rejected: matches physical
  workflow poorly. Drafts are stage-work; final assignment happens
  when the kitchen is ready.
- **Free-form courier limit (no cap).** Rejected: the whole point of
  the per-courier limit is to prevent double-booking. The cap can be
  raised via config but must exist as a default.
- **Support reassignment.** Rejected as out-of-scope. Reassignment
  interacts with the frozen fee, `dispatched_at`, and the
  courier-only dispatch/complete endpoints in ways that need their
  own packet.
