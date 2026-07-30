# Concurrency Limit

The catering operation is intentionally single-threaded by default: at
most one delivery may be active at a time. This document explains the
invariant, its configurability, and the enforcement path. See ADR-012
for the design rationale.

## The invariant

At any point in time, the count of deliveries in non-terminal statuses
must not exceed `config('delivery.max_concurrent_active')`. The default
value is `1`.

Non-terminal statuses are:

- `draft`
- `scheduled`
- `in_transit` (declared but unreachable in Packet 07)

Terminal statuses (`delivered`, `cancelled`) do not count.

## When it is checked

The check runs inside `DeliveryScheduler::assertConcurrencyLimit()`,
executed within the scheduling transaction. It uses the `active` scope
on the `Delivery` model and excludes the current delivery from the
count so the transition itself does not appear as a violation.

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
```

`config/delivery.php`:

```php
'max_concurrent_active' => env('DELIVERY_MAX_CONCURRENT_ACTIVE', 1),
```

## Testing the invariant

- `tests/Feature/Delivery/DeliveryConcurrencyLimitTest.php` covers
  default cap, terminal freeing, multi-slot config, zero cap, and
  draft counting.
- `tests/Unit/Domain/Delivery/DeliverySchedulerTest.php` covers the
  scheduler-level rejection.
