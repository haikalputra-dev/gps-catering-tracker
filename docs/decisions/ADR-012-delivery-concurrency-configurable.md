# ADR-012: Delivery Concurrency Cap is Configurable

- **Status**: Accepted
- **Date**: 2026-07-30
- **Deciders**: Owner + implementation team
- **Approved rows**: AR-27 (with revision note)
- **Packet**: 07

## Context

The catering operation begins as a single-threaded workflow: one
delivery active at a time, matching the reality of one owner and one
courier. Encoding this as a literal `1` throughout the codebase is
tempting but risky. The operation is expected to grow, and later
packets introduce assignment to a courier, then multiple couriers.
Retrofitting a hard-coded constant at that point means touching every
enforcement site.

AR-27 was originally approved as "single active delivery" and revised
to make the cap configurable ahead of implementation. This ADR records
the revision.

## Decision

The concurrency cap is a single configuration value:

```
DELIVERY_MAX_CONCURRENT_ACTIVE=1
```

Read from `config('delivery.max_concurrent_active')` inside
`DeliveryScheduler::assertConcurrencyLimit()`. Default value is `1`,
preserving the original AR-27 intent. Zero is a valid value and blocks
all scheduling (used for planned downtime). Higher values allow
parallel deliveries once the courier surface exists.

Non-terminal statuses count toward the cap:

- `draft`
- `scheduled`
- `in_transit` (declared but unroutable in Packet 07)

Terminal statuses do not count. Drafts count because operators can
stage them; not counting them would allow bypass of the invariant.

The check runs at scheduling time only, not at draft creation. This
allows operators to stage multiple drafts without artificial friction.

## Alternatives considered

**Hard-code `1`.** Rejected. Every future capacity change requires a
code change and a deploy. The invariant is a business rule, not a
correctness rule.

**Feature flag with two modes (`single` vs `multi`).** Rejected.
Adds enum plumbing for no benefit; the integer cap covers both cases
plus the disable-scheduling case.

**Enforce at draft creation.** Rejected. Drafts are a staging area;
blocking their creation would push operators toward workarounds.

**Global lock across the deliveries table.** Rejected. Row-level
locks on the target delivery are sufficient at expected volume; the
cost of table locks is not justified.

## Consequences

Positive:

- Capacity changes are a config edit, not a deploy.
- Zero-cap mode exists for planned downtime.
- Draft-heavy workflows remain unobstructed.
- The enforcement site is one method in one service.

Negative:

- The check can theoretically race under high concurrency. Not a
  practical concern at Packet 07 volume; can be tightened with an
  advisory lock if a real race is observed.

## Compliance

`config/delivery.php` and `.env.example` ship with the default. The
enforcement site is
`app/Domain/Delivery/DeliveryScheduler::assertConcurrencyLimit()`.
Tests:

- `tests/Feature/Delivery/DeliveryConcurrencyLimitTest.php`
- `tests/Unit/Domain/Delivery/DeliverySchedulerTest.php`

Related docs:

- [Concurrency limit](../deliveries/concurrency-limit.md)

## Governance trail

AR-27 was appended to the decision log during Packet 07 governance
setup with a revision note recording the change from "single" to
"configurable, default 1". No prior approved rows were voided.
