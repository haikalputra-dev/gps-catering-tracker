# Snapshots and History

When a delivery transitions from `draft` to `scheduled` the system
captures a point-in-time copy of the kitchen and customer records. This
document explains what is captured, why, and how immutability is
enforced. See ADR-011 for the design rationale.

## What is snapshotted

Ten columns on the `deliveries` table hold the snapshot. They mirror
the source records at the moment of scheduling:

| Delivery column       | Source                     |
| --------------------- | -------------------------- |
| `kitchen_code`        | `kitchens.code`            |
| `kitchen_name`        | `kitchens.name`            |
| `kitchen_address`     | `kitchens.address`         |
| `kitchen_latitude`    | `kitchens.latitude`        |
| `kitchen_longitude`   | `kitchens.longitude`       |
| `customer_name`       | `customers.name`           |
| `customer_phone`      | `customers.phone`          |
| `customer_address`    | `customers.address`        |
| `customer_latitude`   | `customers.latitude`       |
| `customer_longitude`  | `customers.longitude`      |

All ten columns are nullable. Drafts have them set to `NULL`;
snapshots exist only after the `draft to scheduled` transition.

Latitude and longitude are stored as `decimal(10,7)` to match the
source schema and keep sub-meter precision.

## Why snapshot

Kitchens and customers are living records. Their names, addresses, and
coordinates can be edited by operators. Without snapshots, retroactively
editing a customer address would silently mutate the delivery history,
including receipts already handed to customers. Snapshots break that
coupling: the delivery preserves what was true when it was scheduled.

The foreign keys (`kitchen_id`, `customer_id`) remain alongside the
snapshot columns. Reports can still traverse to the live records when
current data is wanted; the snapshot columns are the authoritative
history column for display on delivery receipts and past detail pages.

## Atomicity

Snapshots are populated inside the same database transaction that
generates the receipt and flips status. `DeliveryScheduler::schedule()`:

1. Locks the delivery row with `lockForUpdate()`.
2. Locks the kitchen and customer rows with `lockForUpdate()`.
3. Re-checks preconditions on the freshly locked copies.
4. Copies the ten snapshot columns.
5. Generates the receipt number.
6. Commits.

The `for update` locks guarantee that concurrent edits to kitchen or
customer do not slip between the read and the write.

## Immutability

After scheduling:

- The delivery cannot be edited (status is not `draft`).
- The scheduler is never invoked twice on the same delivery.
- The canceller never touches snapshot columns.
- No admin path rewrites snapshots.
- The frozen pricing values (`distance_km`, `fee_rupiah`) fall under
  the same rule: they are set once in the scheduling transaction and
  preserved on cancellation. See
  [pricing-and-distance.md](pricing-and-distance.md).

`tests/Unit/Domain/Delivery/DeliverySchedulerTest.php::test_snapshots_are_immutable_after_source_edits`
exercises the guarantee by scheduling a delivery, renaming its source
kitchen and customer, and asserting the delivery still reports the
original names.

## Display rules

The show and index views branch on status:

- `draft`: renders the live kitchen and customer via relationship.
- `scheduled`, `cancelled` (from scheduled), and any future terminal
  state: renders the snapshot columns.

The relationship rows remain useful for links back to the live
kitchen and customer profiles even after scheduling; the snapshot
columns are what appear on receipts.

## Not snapshotted

Fields deliberately not captured:

- `kitchens.is_active`, `customers.is_active` (operational flags, not
  historical facts)
- User records (auditors are tracked by user id, not by name)
- Notes (delivery-owned, not source-derived)

## Testing snapshots

- `tests/Unit/Domain/Delivery/DeliverySchedulerTest.php` covers capture
  and immutability.
- `tests/Feature/Delivery/DeliveryStateMachineTest.php::test_scheduled_cancel_preserves_receipt_and_snapshots`
  covers the cancellation preservation path.
