# ADR-011: Delivery Snapshots and Receipt Numbers

- **Status**: Accepted
- **Date**: 2026-07-30
- **Deciders**: Owner + implementation team
- **Approved rows**: AR-24 (receipt), AR-25 (snapshots)
- **Packet**: 07

## Context

Two related requirements land at the same lifecycle event, the
`draft to scheduled` transition:

1. Every scheduled delivery must be assigned a human-friendly receipt
   number that can be quoted to customers, printed on paperwork, and
   used as a stable reference forever.
2. Kitchens and customers are living records whose names, addresses,
   and coordinates can change. A delivery scheduled today must not
   silently mutate tomorrow when the source records are edited.

We must pin both concerns to the same transactional boundary so a
scheduled delivery is always internally consistent.

## Decision

At the `draft to scheduled` transition, inside a single database
transaction:

1. Generate a receipt number of the form `DEL-YYYYMMDD-XXXX` using
   `App\Domain\Delivery\ReceiptNumberGenerator`. The date component
   is in Asia/Jakarta. The suffix is 4 characters from a 30-character
   alphabet that excludes visually confusing characters. Uniqueness is
   enforced by a unique index on `deliveries.receipt_number` with a
   retry loop of up to 10 attempts.
2. Copy ten columns from the referenced kitchen and customer into
   snapshot columns on the delivery row.
3. Record `scheduled_by_user_id` and `scheduled_at_recorded`.
4. Flip status to `scheduled`.

All four operations succeed together or none of them do.
`lockForUpdate()` is applied to the delivery, kitchen, and customer
rows to prevent concurrent writes from producing inconsistent state.

Snapshots are additive columns on the deliveries table (not a separate
history table). Foreign keys to the live records are preserved
alongside them; the snapshot is the authoritative history column and
the FK is the pointer to the current record.

## Alternatives considered

**Auto-increment integer receipt.** Rejected. Not sufficiently opaque
for handing to customers; harder to distinguish at a glance from other
integer identifiers on the site.

**UUID receipt.** Rejected. Not memorable, not printer-friendly, no
built-in date component for filing.

**Separate history table with foreign keys.** Rejected. Adds a join
to every display, complicates reporting, and does not remove the
snapshot requirement (the history rows still need to be atomic with
the state transition).

**Deferred snapshotting on read (materialised view).** Rejected. The
delivery must remain internally consistent forever, even if the source
record is deleted. Snapshotting at write is the only way to guarantee
that without cascading history writes on every kitchen/customer edit.

## Consequences

Positive:

- One transactional boundary owns receipt issuance and history
  capture.
- Deliveries survive kitchen and customer edits without silent
  mutation.
- Receipts are readable, printable, and daily-groupable.
- No new table; joins remain simple.

Negative:

- Ten extra columns on `deliveries`. Manageable at expected volume.
- Retry loop on receipt uniqueness. Bounded at 10 attempts; the
  collision probability at default settings is well below 1 percent
  even at 100 daily deliveries.

## Compliance

`app/Domain/Delivery/ReceiptNumberGenerator.php` owns receipt
generation. `app/Domain/Delivery/DeliveryScheduler.php` owns the
transactional boundary. Tests:

- `tests/Unit/Domain/Delivery/ReceiptNumberGeneratorTest.php`
- `tests/Unit/Domain/Delivery/DeliverySchedulerTest.php`
- `tests/Feature/Delivery/DeliveryStateMachineTest.php`

Related docs:

- [Receipt numbers](../deliveries/receipt-numbers.md)
- [Snapshots and history](../deliveries/snapshots-and-history.md)
