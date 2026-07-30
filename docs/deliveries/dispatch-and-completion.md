# Dispatch and Completion

Once a delivery is scheduled and assigned to a courier, two more
transitions remain: `scheduled → in_transit` (dispatch) and
`in_transit → delivered` (completion). Both are manual, both are
initiated by the assigned courier, and both persist a UTC timestamp
on the delivery row. See ADR-015 for the design rationale.

## Endpoints

Two courier-only routes on the delivery resource:

| Method | Path                                     | Name                     | Purpose                                  |
| ------ | ---------------------------------------- | ------------------------ | ---------------------------------------- |
| POST   | `/deliveries/{delivery}/dispatch`        | `deliveries.dispatch`    | Courier taps "Start Delivery".           |
| POST   | `/deliveries/{delivery}/mark-delivered`  | `deliveries.mark-delivered` | Courier taps "Mark Delivered".        |

Both sit inside a `role:courier` middleware group in
`routes/web.php`. There are no alternative aliases
(`complete`, `deliver`, `finish`, `arrive`, `arrived` all 404;
verified by `DeliveryRouteTest`).

Both endpoints redirect back to `deliveries.show` on success with a
`status` flash message and on validation failure with a
`withErrors(['status' => ...])` bag. This gives the courier a
consistent surface to read the outcome without having to swap views.

## Dispatch (`scheduled → in_transit`)

Invariants enforced by `DeliveryDispatcher::dispatch()`:

1. Source state must be `scheduled`. Any other state (draft,
   in_transit, delivered, cancelled) raises
   `NotDispatchableStateException`.
2. `courier_id` must be non-null. A null value at this stage is a
   programmer error (the scheduler should have prevented it) and
   raises `MissingCourierException`.
3. The acting user (`$request->user()`) must equal the assigned
   courier by `id`. Different-courier attempts raise
   `NotAssignedCourierException`.
4. The assigned courier must still be `is_active = true`. Otherwise
   `InactiveCourierException`.

On success the dispatcher writes, atomically within a DB transaction:

- `status = 'in_transit'`
- `dispatched_at = Carbon::now('UTC')` as a naked `Y-m-d H:i:s` string

Snapshot columns (`kitchen_*`, `customer_*`, `receipt_number`,
`distance_km`, `fee_rupiah`) are untouched.

## Completion (`in_transit → delivered`)

Invariants enforced by `DeliveryCompleter::complete()`:

1. Source state must be `in_transit`. Any other state raises
   `NotCompletableStateException`.
2. Acting user must be the assigned courier
   (`NotAssignedCourierException` otherwise).
3. Assigned courier must still be active
   (`InactiveCourierException` otherwise).

On success the completer writes:

- `status = 'delivered'`
- `delivered_at = Carbon::now('UTC')` as a naked `Y-m-d H:i:s` string

The completer NEVER rewrites `dispatched_at`. `delivered_at` is
guaranteed monotonic with respect to `dispatched_at`
(`delivered_at >= dispatched_at`) because both use the same
UTC-monotonic clock and `dispatched_at` was written earlier in
wall-clock time.

## Timestamp storage and reading

Both `dispatched_at` and `delivered_at` are:

- Persisted as UTC via `Carbon::now('UTC')` written as a raw string.
- Cast on the Eloquent model as `datetime`, which returns Carbon
  instances in the application timezone (`Asia/Jakarta`) on read.
- Preserved across cancellation. A row that was previously
  `in_transit` and then cancelled retains its `dispatched_at` for
  post-hoc auditing.

Unit tests that assert exact timestamp equality bypass the Eloquent
cast by reading raw `DB::table('deliveries')` rows and parsing them
with `Carbon::createFromFormat('Y-m-d H:i:s', $raw, 'UTC')`. This
avoids a tz-double-conversion pitfall on SQLite `:memory:`.

## What dispatch and completion do NOT do

- No customer notification (no SMS, push, or email in this packet).
- No GPS proximity check or auto-triggering.
- No customer-side confirmation.
- No fee adjustment. The fee is already frozen.
- No snapshot mutation. Addresses and coordinates never change.
- No reassignment. If the courier can't complete, the office
  cancels and re-drafts (see courier-assignment.md).

## Error surface

| Exception                              | HTTP effect                                            |
| -------------------------------------- | ------------------------------------------------------ |
| `NotDispatchableStateException`        | Redirect to show with session error `status`.          |
| `NotCompletableStateException`         | Redirect to show with session error `status`.          |
| `NotAssignedCourierException`          | Redirect to show with session error `status`.          |
| `InactiveCourierException`             | Redirect to show with session error `status`.          |
| `MissingCourierException`              | Redirect to show with session error `status`.          |
| Role mismatch (not a courier)          | 403 from the `role:courier` middleware.                |
| Inactive courier at auth time          | 302 redirect to `/login` (auth layer treats as guest). |

The controller catches all typed domain exceptions and normalises
the response. Route-layer failures (403, redirect to login) come
straight from the middleware chain.
