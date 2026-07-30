# Delivery Requirements

Source-of-truth requirements for delivery orders. Scope is Packet 07:
drafts, scheduling to `scheduled`, cancellation from `draft` or
`scheduled`, receipt-number issuance, kitchen and customer snapshots,
concurrency cap.

## R-DEL-01: State model

Deliveries exist in exactly one of five states at any moment: `draft`,
`scheduled`, `in_transit`, `delivered`, `cancelled`. The status column
is a non-null string with default `draft`. Enum values are stable and
persisted verbatim.

## R-DEL-02: Implemented transitions

Packet 07 implements three transitions:

- `draft to scheduled` via `DeliveryScheduler`
- `draft to cancelled` via `DeliveryCanceller`
- `scheduled to cancelled` via `DeliveryCanceller`

The enum declares two more (`scheduled to in_transit`, `in_transit to
delivered`) but no route exercises them yet. Terminal states have no
outgoing transitions.

## R-DEL-03: Draft creation

Any authenticated `owner` or `staff` user can create a draft. Required
fields: `kitchen_id`, `customer_id`. Optional: `scheduled_at`, `notes`.
Snapshot columns and audit columns (`scheduled_by_user_id`,
`scheduled_at_recorded`, cancellation fields, receipt) are `NULL`.
`created_by_user_id` is set to the acting user id.

## R-DEL-04: Draft editability

Only drafts are editable. Edit routes reject any other status by
returning a redirect with a session error. Kitchen and customer must
remain active on update.

## R-DEL-05: Scheduling preconditions

The scheduler rejects the transition unless all preconditions hold:

- Status is `draft`
- Kitchen id, customer id, and `scheduled_at` are present
- `scheduled_at` is strictly in the future (UTC comparison)
- The referenced kitchen is `is_active = true`
- The referenced customer is `is_active = true`
- The active delivery count (excluding this one) is below the cap

## R-DEL-06: Receipt number issuance

On successful `draft to scheduled` transition the delivery is assigned
a receipt of the form `DEL-YYYYMMDD-XXXX`. The date component is in
Asia/Jakarta. The suffix is 4 characters from
`ABCDEFGHJKMNPQRSTUVWXYZ23456789`. The value is unique across the
`deliveries` table and is never rewritten.

## R-DEL-07: Snapshots

On successful `draft to scheduled` transition the delivery captures a
snapshot of ten columns (five kitchen, five customer). Snapshots are
immutable after generation: subsequent edits to the source kitchen or
customer records do not propagate.

## R-DEL-08: Cancellation

Cancellation is allowed from `draft` and `scheduled`. A cancellation
reason is required, trimmed length in `[3, 255]`. Cancellation records
the acting user id and UTC timestamp. Receipt and snapshot data
recorded before cancellation are preserved.

## R-DEL-09: Concurrency cap

At most `config('delivery.max_concurrent_active')` deliveries may be
non-terminal at any time (default 1). Enforced at scheduling. Drafts
count toward the cap. Zero disables scheduling entirely.

## R-DEL-10: Audit fields

Every delivery records `created_by_user_id`. Scheduling records
`scheduled_by_user_id` and `scheduled_at_recorded`. Cancellation
records `cancelled_by_user_id` and `cancelled_at`. All are UTC.

## R-DEL-11: Authorization

Deliveries are accessible only to authenticated `owner` and `staff`
users whose account is active. Couriers receive `403 Forbidden`.
Guests are redirected to `/login`.

## R-DEL-12: No API surface

No `/api/deliveries` route is registered in Packet 07. No public
tracking route is registered. Web routes are session-based and CSRF
protected.

## R-DEL-13: No hard delete

No `DELETE` route is registered. Deliveries are never destroyed;
cancellation is the terminal disposal path.

## Out of scope

- Courier assignment, dispatch, in-transit, delivered
- Distance, fees, Haversine math
- SMS notifications, GPS telemetry, firmware integration
- Receipt tracking page and authorization tokens
- API endpoints, mobile surfaces

## Traceability

| Requirement    | Approved row | Implementing artifact                          |
| -------------- | ------------ | ---------------------------------------------- |
| R-DEL-01, 02   | AR-23        | `DeliveryStatus` enum, `DeliveryScheduler`     |
| R-DEL-06       | AR-24        | `ReceiptNumberGenerator`                       |
| R-DEL-07       | AR-25        | `DeliveryScheduler::schedule()`                |
| R-DEL-08       | AR-26        | `DeliveryCanceller`, cancellation FormRequest  |
| R-DEL-09       | AR-27        | `DeliveryScheduler::assertConcurrencyLimit()`  |
| R-DEL-10       | AR-28        | Audit columns on `deliveries` table            |
