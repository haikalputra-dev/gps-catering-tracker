# Courier Assignment

Each delivery is tied to exactly one courier before it leaves the
kitchen. This document explains the assignment lifecycle, the
per-courier concurrency cap, and how deactivation and reassignment
are handled. See ADR-014 for the design rationale.

## Assignment lifecycle

The `deliveries.courier_id` foreign key is nullable and follows this
lifecycle:

| State        | `courier_id`  | Notes                                        |
| ------------ | ------------- | -------------------------------------------- |
| `draft`      | nullable      | Office may pick the courier later.           |
| `scheduled`  | **required**  | Enforced by `DeliveryScheduler`.             |
| `in_transit` | required      | Same value as at scheduling time.            |
| `delivered`  | required      | Preserved as historical record.              |
| `cancelled`  | required-or-null | Whatever value was set when cancel fired. |

The scheduler asserts a non-null `courier_id` before any snapshot,
receipt, distance, or fee work happens. If the courier is missing at
scheduling time, `MissingCourierException` is thrown and the entire
transaction rolls back.

## Who can be assigned

Only a user with:

- `role = 'courier'`
- `is_active = true`

...is a valid target. Assigning a delivery to a non-courier user (an
owner or staff account, for instance) or to a deactivated courier
raises `CourierNotCourierRoleException` and
`InactiveCourierException` respectively.

The scheduler and both field endpoints (`dispatch`, `mark-delivered`)
re-check `is_active` at their own transaction time. A courier who was
active at scheduling but deactivated before dispatch cannot fire the
courier-side transitions on the deactivated account.

## Per-courier concurrency cap

The scheduler counts how many deliveries the target courier already
has in an active state (`draft`, `scheduled`, `in_transit`) using the
`active` scope on `Delivery`. If that count is at or above:

```
config('delivery.max_concurrent_per_courier')
```

...the transition is refused with
`CourierConcurrencyLimitReachedException`.

The default is `1`: at most one active delivery per courier at any
time. The value is overridable via the `DELIVERY_MAX_PER_COURIER_ACTIVE`
env variable. Setting the cap to `0` or negative blocks all courier
assignment.

The cap is enforced at scheduling only. It is not enforced on draft
creation, because drafts without an assigned courier do not count
against any courier's cap, and drafts with an assigned courier are
counted as active for that courier.

## Reassignment is not supported

Once `courier_id` is set on a `scheduled`, `in_transit`, or terminal
delivery, it cannot be changed. The `edit` route does not expose a
courier control after scheduling, and there is no dedicated reassign
endpoint (AR-36).

If a courier becomes unavailable mid-route, the workflow is:

1. Owner or staff cancels the delivery with a reason.
2. Office creates a new draft for a different courier.

This trades a small amount of UX friction for a much simpler
invariant set: the frozen fee is unambiguously tied to one courier,
and `dispatched_at` never has to answer "which courier?"

## Deactivation semantics

Deactivating a courier (`is_active = false`) has these effects on
open deliveries:

- **Draft, no `courier_id` yet**: unaffected. The courier was never
  assigned.
- **Draft, `courier_id` set to the deactivated user**: cannot
  schedule. `DeliveryScheduler` re-checks `is_active` and refuses.
- **Scheduled or in-transit**: office can still cancel from the
  scheduled or in_transit state (AR-38 revised). The deactivated
  courier themselves cannot fire dispatch or mark-delivered from
  their (logged-out) account.

Reactivating the courier (`is_active = true`) restores their ability
to act on any deliveries still assigned to them.

## Failure modes

| Exception                                             | Trigger                                              |
| ----------------------------------------------------- | ---------------------------------------------------- |
| `MissingCourierException`                             | `courier_id = null` at scheduling.                   |
| `CourierNotCourierRoleException`                      | Assigned user is not `role = 'courier'`.             |
| `InactiveCourierException`                            | Assigned courier has `is_active = false`.            |
| `CourierConcurrencyLimitReachedException`             | Courier's active count is at the cap.                |
| `NotAssignedCourierException`                         | Dispatch/complete called by a different courier.     |

All are typed exceptions in `App\Domain\Delivery\Exceptions`. The
`DeliveryController` catches them and redirects with a session error
so the operator sees a human-readable message.
