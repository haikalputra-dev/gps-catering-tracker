# ADR-010: Delivery State Machine

- **Status**: Accepted
- **Date**: 2026-07-30
- **Deciders**: Owner + implementation team
- **Approved rows**: AR-23 (state model), AR-26 (cancellation)
- **Packet**: 07

## Context

Delivery orders are the operational unit of the catering tracker. They
progress through a lifecycle from creation, through scheduling,
transit, and delivery. At each step certain fields are read-only,
audit trails must be kept, and only some transitions are allowed. We
need a durable finite state machine, encoded in one place, with clear
guarantees the routing layer can rely on.

## Decision

Encode the delivery lifecycle as a five-value backed enum
`App\Domain\Delivery\DeliveryStatus` with values `draft`, `scheduled`,
`in_transit`, `delivered`, `cancelled`. The enum owns the truth about:

- Which statuses are editable (`isEditable`)
- Which statuses count toward the concurrency cap
  (`isActiveForConcurrency`)
- Which statuses are terminal (`isTerminal`)
- Which transitions are allowed (`canTransitionTo`)

Only the transitions needed by Packet 07 are wired to routes:
`draft to scheduled`, `draft to cancelled`, `scheduled to cancelled`.
The remaining transitions (`scheduled to in_transit`,
`in_transit to delivered`) are declared allowed in `canTransitionTo`
so that future packets can wire them without revisiting the enum.

Business logic for each transition lives in a dedicated domain
service, not in the controller:

- `DeliveryScheduler` for `draft to scheduled`
- `DeliveryCanceller` for both cancellation paths

Each service runs inside a database transaction, locks the delivery
row with `lockForUpdate()`, and emits typed exceptions on rejection.

## Alternatives considered

**String constants on the model.** Rejected: no compile-time
enforcement, easy to typo, no natural home for `canTransitionTo`.

**Full state-machine library (winzou/state-machine).** Rejected: the
lifecycle is small enough that a library adds more indirection than it
removes. A backed enum plus a small helper table on the enum is
sufficient.

**Transition logic in the controller.** Rejected: services are
testable in isolation, controllers become thin adapters, and the
transaction boundary is closer to the invariants it protects.

## Consequences

Positive:

- One canonical answer to "what states exist" and "what transitions
  are allowed" (the enum).
- Domain services are unit-testable without HTTP scaffolding.
- FormRequests can `authorize()` off `status`, enforcing edit rules
  before validation runs.
- Adding future transitions is a route + service change; the enum
  already permits them.

Negative:

- Two layers (enum plus service) require the developer to know where
  each concern lives. The service layer is the enforcement point; the
  enum is the vocabulary.

## Compliance

The enum lives at `app/Domain/Delivery/DeliveryStatus.php`. Unit tests
in `tests/Unit/Domain/Delivery/DeliveryStatusTest.php` verify the
truth tables and transition table. Feature tests in
`tests/Feature/Delivery/DeliveryStateMachineTest.php` verify the routed
transitions end-to-end.
