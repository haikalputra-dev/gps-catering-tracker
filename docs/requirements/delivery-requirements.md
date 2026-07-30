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

## Distance and fee (Packet 08)

### DEL-FR-031: Distance capture

At `draft to scheduled`, the delivery captures a straight-line Haversine
distance in kilometres between the snapshot kitchen coordinates and the
snapshot customer coordinates. Stored in `distance_km` as
`decimal(8, 3)`, rounded half-up to 3 decimal places.

### DEL-FR-032: Fee capture

At `draft to scheduled`, the delivery captures a rupiah fee in
`fee_rupiah` (`unsigned int`). Formula:

```
raw       = distance_km * pricing.rate_per_km_rupiah
rounded   = round(raw / pricing.fee_rounding_step_rupiah, 0, HALF_UP)
            * pricing.fee_rounding_step_rupiah
fee       = max(pricing.minimum_fee_rupiah, rounded)
```

### DEL-FR-033: Configuration

The formula reads three keys from `config/pricing.php` on every call
(`minimum_fee_rupiah`, `rate_per_km_rupiah`, `fee_rounding_step_rupiah`)
which resolve to env variables `PRICING_MINIMUM_FEE_RUPIAH`,
`PRICING_RATE_PER_KM_RUPIAH`, `PRICING_FEE_ROUNDING_STEP_RUPIAH`.
Defaults are `5000`, `2000`, `100`. The Earth radius `6371.0088` km is
a class constant, not configurable.

### DEL-FR-034: Immutability

Once populated, `distance_km` and `fee_rupiah` are never rewritten.
Cancellation preserves both. No route or service recomputes them.

### DEL-FR-035: Display

Both values render only to authenticated owner and staff users on the
delivery index (fee column) and delivery show page (pricing card).
Rupiah format `Rp 12.345` (Indonesian dot-thousands). Draft rows show
a placeholder dash.

### DEL-FR-036: Draft nullability

While `status = draft`, both columns are `NULL`. Any transition that
does not reach `scheduled` leaves them `NULL`.

## Acceptance criteria (Packet 08)

- **DEL-AC-041:** `deliveries.distance_km` is `decimal(8, 3) NULL`
  positioned after `customer_longitude`.
- **DEL-AC-042:** `deliveries.fee_rupiah` is `unsigned int NULL`
  positioned after `distance_km`.
- **DEL-AC-043:** A newly created draft has `distance_km = NULL` and
  `fee_rupiah = NULL`.
- **DEL-AC-044:** Scheduling a draft populates both columns in the
  same transaction that captures the address snapshot and generates
  the receipt.
- **DEL-AC-045:** `distance_km` equals the Haversine distance between
  the snapshot coordinates rounded half-up to 3 decimals.
- **DEL-AC-046:** `fee_rupiah` equals the formula in DEL-FR-032
  applied to the stored `distance_km`.
- **DEL-AC-047:** Overriding any of the three `pricing.*` config keys
  changes the computed fee for the next scheduling only; existing
  scheduled deliveries are unaffected.
- **DEL-AC-048:** Cancelling a scheduled delivery preserves both
  values byte-for-byte.
- **DEL-AC-049:** Editing the source kitchen or customer coordinates
  after scheduling does not change the stored `distance_km` or
  `fee_rupiah`.
- **DEL-AC-050:** The delivery show page renders a Pricing card with
  distance, fee, and an explanatory note when either value is set.
- **DEL-AC-051:** The delivery index adds a Fee column showing
  `Rp <formatted>` for scheduled or cancelled-from-scheduled rows and
  a placeholder for others.
- **DEL-AC-052:** Guests, couriers, and inactive users cannot reach
  the pricing surface (auth and role middleware are unchanged from
  Packet 07 and cover the new column and card).
- **DEL-AC-053:** No new route, controller action, or FormRequest is
  introduced.
- **DEL-AC-054:** The Haversine calculator rejects out-of-range
  latitudes or longitudes with `InvalidArgumentException`.
- **DEL-AC-055:** The pricing calculator rejects negative distance and
  non-positive rounding step with `InvalidArgumentException`.

## Courier lifecycle (Packet 09)

### DEL-FR-037: Courier column

Deliveries carry a `courier_id` foreign key to `users`. Nullable at
`draft`, required at scheduling (AR-37). No reassignment after
scheduling (AR-36).

### DEL-FR-038: Assignment eligibility

An assignable courier is a user with `role = 'courier'` and
`is_active = true`. Violations raise
`CourierNotCourierRoleException` or `InactiveCourierException`.

### DEL-FR-039: Per-courier concurrency cap

At scheduling time, the target courier's count of active deliveries
(`draft`, `scheduled`, `in_transit`) must be strictly less than
`config('delivery.max_per_courier_active')` (default `1`). Breach
raises `CourierConcurrencyLimitReachedException` (AR-34).

### DEL-FR-040: Dispatch endpoint

`POST /deliveries/{delivery}/dispatch` (name
`deliveries.dispatch`) is courier-only. It flips `scheduled →
in_transit`, writes `dispatched_at = Carbon::now('UTC')`, and only
the assigned, still-active courier may fire it. All other calls
raise typed exceptions (AR-35).

### DEL-FR-041: Completion endpoint

`POST /deliveries/{delivery}/mark-delivered` (name
`deliveries.mark-delivered`) is courier-only. It flips `in_transit
→ delivered`, writes `delivered_at = Carbon::now('UTC')`, only the
assigned, still-active courier may fire it, and `delivered_at >=
dispatched_at` is guaranteed monotonic (AR-35).

### DEL-FR-042: Mid-route cancellation

`in_transit` is cancellable. Owner and staff may cancel any
non-terminal state; a courier may cancel only their own
`in_transit` delivery. Terminal states remain terminal. Blank
reasons remain rejected (AR-38 revised).

### DEL-FR-043: No `failed` state

The state machine keeps its five states. There is no `failed`
terminal state; failed deliveries are cancelled with a reason
(AR-39).

### DEL-FR-044: Fee privacy for couriers

Neither `fee_rupiah` nor `distance_km` may appear on any
courier-facing surface (show page for assigned courier, courier
dashboard). Owner and staff continue to see both (AR-40).

### DEL-FR-045: Route surface

Exactly ten delivery routes exist:
`index`, `create`, `store`, `edit`, `update`, `schedule`, `show`,
`cancel`, `dispatch`, `mark-delivered`. No alternative dispatch or
complete aliases are registered (AR-41).

## Acceptance criteria (Packet 09)

- **DEL-AC-056:** Scheduling refuses when `courier_id` is null.
- **DEL-AC-057:** Scheduling refuses when the target user is not a
  courier.
- **DEL-AC-058:** Scheduling refuses when the target courier is
  `is_active = false`.
- **DEL-AC-059:** Scheduling refuses when the target courier is
  already at the per-courier cap.
- **DEL-AC-060:** Raising the cap via env lets a courier take on
  more than one active delivery, without a code change.
- **DEL-AC-061:** Dispatch stamps `dispatched_at` and rejects
  non-scheduled sources.
- **DEL-AC-062:** Completion stamps `delivered_at` and rejects
  non-in_transit sources.
- **DEL-AC-063:** Dispatch and completion never mutate the frozen
  snapshot columns.
- **DEL-AC-064:** Cancellation from `in_transit` succeeds for
  owner, staff, and the assigned courier; is rejected for other
  couriers.
- **DEL-AC-065:** Courier show page never renders `fee_rupiah`,
  `distance_km`, or the Pricing section.
- **DEL-AC-066:** Courier dashboard is scoped to the acting
  courier's own active deliveries only.
- **DEL-AC-067:** `routes/web.php` registers exactly ten delivery
  routes; `complete`, `deliver`, `finish`, `arrive`, `arrived` all
  404.

## Out of scope

- Reassignment after scheduling
- `failed` terminal state
- Customer-side confirmation, SMS, push, or email notifications
- GPS proximity triggering, device provisioning, telemetry
- Real-time map updates for customer or office
- Fee recalculation on cancellation
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
| DEL-FR-031..036| AR-29..AR-33 | `DistanceCalculator`, `PricingCalculator`,     |
|                |              | `DeliveryScheduler` integration                |
| DEL-FR-037..045| AR-34..AR-41 | `courier_id`, `dispatched_at`, `delivered_at`, |
|                |              | `DeliveryDispatcher`, `DeliveryCompleter`,     |
|                |              | courier dashboard, fee-privacy Blade branch    |
