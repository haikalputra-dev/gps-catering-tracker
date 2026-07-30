# Concurrency Limit

The catering operation is intentionally single-threaded by default: at
most one delivery may be active at a time. Packet 09 adds a second,
per-courier cap on top of the global cap. This document explains both
invariants, their configurability, and the enforcement path. See
ADR-012 (global cap) and ADR-014 (per-courier cap) for the design
rationale.

## The global invariant

At any point in time, the count of deliveries in non-terminal statuses
must not exceed `config('delivery.max_concurrent_active')`. The default
value is `1`.

Non-terminal statuses are:

- `draft`
- `scheduled`
- `in_transit` (declared but unreachable in Packet 07)

Terminal statuses (`delivered`, `cancelled`) do not count.

## The per-courier invariant

For any single courier, the count of deliveries assigned to them in
non-terminal statuses must not exceed
`config('delivery.max_concurrent_per_courier')`. The default value is
`1`.

This is a distinct check from the global cap:

- The global cap (`max_concurrent_active`) looks at ALL active
  deliveries in the system.
- The per-courier cap (`max_concurrent_per_courier`) looks only at
  deliveries with a given `courier_id`.

Both must be satisfied for scheduling to succeed. A single-courier
operation running at defaults hits both caps at the same point (one
active delivery in the system, assigned to the one courier). A
future two-courier operation with global cap `2` and per-courier
cap `1` prevents accidental double-booking of a single person.

## When it is checked

The check runs inside `DeliveryScheduler::assertConcurrencyLimit()`
(global) and `DeliveryScheduler::assertCourierCapacity()`
(per-courier), both executed within the scheduling transaction. Both
use the `active` scope on the `Delivery` model and exclude the
current delivery from the count so the transition itself does not
appear as a violation.

The check does not run:

- On draft creation. Drafts count against the cap but the cap is only
  enforced at scheduling. Operators can freely stage multiple drafts
  and pick one to schedule.
- On cancellation. Cancelling a delivery moves it to a terminal state
  and can only free capacity.

## Zero-limit semantics

Setting `max_concurrent_active` to `0` (or negative) blocks all
scheduling. This is a supported operating mode for planned downtime:
drafts can still be created and cancelled, but nothing transitions to
`scheduled`.

The scheduler recognises this case explicitly, throwing
`ConcurrencyLimitReachedException` before running the count query.

## Multi-slot configuration

Setting `max_concurrent_active` above `1` allows the operator to run
multiple parallel deliveries. This is intended for future growth (more
than one courier, staggered kitchens). The math and the enforcement
path are unchanged; the value is read from config on every scheduling
attempt so no restart is needed to adjust capacity.

## Why drafts count

Drafts occupy an operator's attention. If the cap were only counting
`scheduled` and `in_transit`, an operator could stage 20 drafts and
schedule them in a tight loop, bypassing the intent of the invariant.
Counting drafts keeps the queue realistic.

Because the cap is only enforced at scheduling, staging drafts does
not itself block anything. It merely delays their promotion until an
active delivery reaches a terminal state.

## Concurrency safety

`assertConcurrencyLimit()` runs inside a transaction that has already
locked the target delivery row with `lockForUpdate()`. That is not
enough to serialise the count query itself; two concurrent schedulers
could still race. The current implementation relies on MySQL's
InnoDB row locking on the target delivery and accepts the theoretical
race window at cap boundaries. In practice, the catering workflow has
a single operator scheduling at a time; a stricter serialisation
(advisory lock, table lock, `insert ... select for update`) can be
added if a real race is observed.

## Configuration

`.env`:

```
DELIVERY_MAX_CONCURRENT_ACTIVE=1
DELIVERY_MAX_CONCURRENT_PER_COURIER=1
```

`config/delivery.php`:

```php
'max_concurrent_active'      => env('DELIVERY_MAX_CONCURRENT_ACTIVE', 1),
'max_concurrent_per_courier' => env('DELIVERY_MAX_CONCURRENT_PER_COURIER', 1),
```

Zero-or-negative on either key blocks scheduling. Values are read
from config on every scheduling attempt so no restart is needed to
adjust capacity.

## Testing the invariant

- `tests/Feature/Delivery/DeliveryConcurrencyLimitTest.php` covers
  default cap, terminal freeing, multi-slot config, zero cap, and
  draft counting.
- `tests/Unit/Domain/Delivery/DeliverySchedulerTest.php` covers the
  scheduler-level rejection.
