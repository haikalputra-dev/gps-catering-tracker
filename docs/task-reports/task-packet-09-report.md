# Task Packet 09 Report: Delivery Courier Lifecycle

- **Packet**: 09
- **Slice**: Courier assignment at scheduling, courier-initiated
  `scheduled -> in_transit -> delivered` taps, mid-route cancellation,
  per-courier concurrency cap, courier dashboard, and fee privacy.
- **Starting commit**: `57eb12a feat: add Haversine distance and fee
  calculation to delivery scheduling`
- **Branch**: `main`
- **Date completed**: 2026-07-30

## Scope summary

Packet 09 completes the delivery state machine by binding a courier to
each delivery at scheduling time and adding the two remaining forward
transitions plus a mid-route cancellation path:

- `courier_id` (`unsignedBigInteger`, FK to `users`): the courier
  assigned to this delivery. Nullable while `draft`, required at
  `scheduled` and thereafter. Persisted in a new migration.
- `dispatched_at` (`datetime`, UTC): stamped when the assigned courier
  transitions `scheduled -> in_transit` via `DeliveryDispatcher`.
- `delivered_at` (`datetime`, UTC): stamped when the assigned courier
  transitions `in_transit -> delivered` via `DeliveryCompleter`.

The five-state enum `draft, scheduled, in_transit, delivered,
cancelled` is unchanged. No new state was added. `AR-39` was ratified
to explicitly forbid a "failed" state; abnormal endings ride the
existing `cancelled` terminal.

Cancellation now accepts `in_transit` as a source state (`AR-38`
revised). Owner and staff may cancel any non-terminal delivery
(`draft`, `scheduled`, `in_transit`). A courier may cancel only their
own `in_transit` delivery. The distance and fee fields remain hidden
from every courier-facing surface (`AR-40`). Exactly ten delivery
routes exist in `routes/web.php` (`AR-41`).

Explicitly out of scope: telemetry ingestion, GPS coordinates on live
delivery rows, customer-facing surfaces, real-time maps, device
provisioning, SMS notifications, firmware, courier reassignment mid
route, any new lifecycle state, and any change to the `/home/ubuntu/
GPS-server` project.

## Deliverables

### Governance

- `AR-34..AR-41` appended to `docs/project/decision-log.md`.
- `AR-38` marked "Approved (revised)" (cancellation from `in_transit`
  clarified pre-implementation; courier authorization scope narrowed
  to their own row).
- Packet 09 governance-audit note recorded: no invalid entries voided
  between `AR-34` and `AR-41`; the range was appended cleanly on top of
  the Packet 08 governance floor.

### Configuration

- `config/delivery.php`: existing `max_concurrent_active` retained; new
  key `max_concurrent_per_courier` (env
  `DELIVERY_MAX_CONCURRENT_PER_COURIER`, default `1`) added at line 54.
- `.env.example` appended with `DELIVERY_MAX_CONCURRENT_PER_COURIER=1`.
  `.env` untouched.

### Persistence

- Migration
  `2026_07_30_150000_add_courier_assignment_to_deliveries_table.php`
  adding:
  - `courier_id unsignedBigInteger NULL` FK to `users(id)` on delete
    `restrict` (couriers with historical deliveries cannot be deleted),
  - `dispatched_at datetime NULL`,
  - `delivered_at datetime NULL`,
  positioned after `fee_rupiah`. All three are nullable so pre-Packet-09
  rows (drafts, cancelled-from-draft) remain valid.
- `App\Models\Delivery`: `courier_id`, `dispatched_at`, `delivered_at`
  added to `$fillable`; casts `dispatched_at => datetime`,
  `delivered_at => datetime`. New `courier()` `BelongsTo` relation.
  New `scopeActiveForCourier($query, int $courierId)` used by the
  courier dashboard and per-courier cap check.

### Domain layer (`app/Domain/Delivery`)

- `DeliveryScheduler`: constructor unchanged; the payload accepted by
  `schedule()` now requires a `courier_id`. Before locking snapshot
  rows, the scheduler resolves the courier user, asserts
  `role === 'courier'`, asserts `is_active === true`, and asserts the
  courier's active-delivery count is below
  `config('delivery.max_concurrent_per_courier')`. On success,
  `courier_id` is written in the same `forceFill` that captures the
  snapshot, receipt, distance, and fee.
- `DeliveryDispatcher::dispatch(Delivery, User)` new service.
  Transactional; locks the delivery row; asserts status is `scheduled`
  and the actor is the assigned courier; stamps `status = in_transit`
  and `dispatched_at = Carbon::now('UTC')`.
- `DeliveryCompleter::complete(Delivery, User)` new service.
  Transactional; locks the delivery row; asserts status is `in_transit`
  and the actor is the assigned courier; stamps `status = delivered`
  and `delivered_at = Carbon::now('UTC')`.
- `DeliveryCanceller::cancel(Delivery, User, string $reason)` extended
  matrix:
  - `draft`, `scheduled`, or `in_transit` are cancellable.
  - Owner and staff may cancel any non-terminal row.
  - A courier may cancel only their own `in_transit` row.
  - `delivered` and `cancelled` reject with
    `NotCancellableStateException`.
  - Snapshot, receipt, courier_id, dispatched_at, distance, and fee are
    all preserved on cancel; only cancellation columns are written.

### Exceptions (`app/Domain/Delivery/Exceptions`)

Eight typed exceptions added in this packet:

- `MissingCourierException` (scheduling payload lacks a courier_id).
- `CourierNotCourierRoleException` (assigned user's role is not
  `courier`).
- `InactiveCourierException` (assigned courier is deactivated).
- `CourierConcurrencyLimitReachedException` (per-courier cap hit).
- `NotAssignedCourierException` (dispatch/complete/mid-route-cancel
  by a courier who is not the assignee).
- `NotDispatchableStateException` (dispatch called on non-`scheduled`).
- `NotCompletableStateException` (complete called on non-`in_transit`).
- `NotAuthorizedToCancelException` (cancel policy failure surfaced
  from `CancelDeliveryRequest::authorize()`).

`ConcurrencyLimitReachedException` and `NotCancellableStateException`
from Packet 07 continue to serve their original roles.

### HTTP layer

- `DeliveryController` gains two actions:
  - `dispatch(Delivery, DeliveryDispatcher)` handling
    `POST /deliveries/{delivery}/dispatch`.
  - `markDelivered(Delivery, DeliveryCompleter)` handling
    `POST /deliveries/{delivery}/mark-delivered`.
  Each catches its typed domain exception, maps it to a redirect back
  to `route('deliveries.show', $delivery)` with a validation error or
  status flash message. Success redirects to the same `show` route
  with a `status` flash. Neither action redirects to the courier
  dashboard.
- `DeliveryController::store` and `update` extend the accepted payload
  with `courier_id` (validated as `exists:users,id` and role/active
  by the scheduler when the draft is scheduled).
- `DeliveryController::show` enforces courier visibility: a courier
  can view a delivery only if `courier_id` matches their user id;
  otherwise a 403 is returned.
- `CancelDeliveryRequest::authorize()`: owner and staff pass for any
  non-terminal row; courier passes only if the row's `courier_id`
  matches the actor and status is `in_transit`. Failure raises
  `HttpResponseException` with a redirect + `withErrors()` payload so
  feature tests use `assertSessionHasErrors('status')`, not
  `assertForbidden()`.
- `DashboardController::courier()` new action. Loads at most one
  active delivery for the authenticated courier via
  `Delivery::activeForCourier($user->id)->first()`, ordered by
  `scheduled_at ASC`, and passes it to the `dashboard.courier` view.
  Non-couriers hit this route via the role middleware and receive
  403.
- `routes/web.php`: ten delivery routes total (index, create, store,
  show, edit, update, schedule, dispatch, mark-delivered, cancel).
  Courier dashboard route named `courier.dashboard`. All delivery
  routes are grouped under `auth` + `RequireRole` middleware. The
  `RequireRole` middleware supports comma-separated role lists.

### Presentation

- `resources/views/dashboard/courier.blade.php` (104 lines) new. Shows
  the single active delivery for the authenticated courier with:
  - customer name, address, and phone,
  - kitchen name and coordinates,
  - a status pill,
  - a "Start Delivery" button when status is `scheduled`,
  - a "Mark Delivered" button when status is `in_transit`,
  - an empty-state block with the exact string `No active delivery`
    when the courier has no active row (empty state must match the
    test suite verbatim).
  No `fee_rupiah`, no `distance_km`, no Pricing card on this surface.
- `resources/views/deliveries/show.blade.php`: existing Pricing card
  wrapped in an office-only branch. Owner and staff continue to see
  distance and fee; couriers viewing their own assigned row do not.
  A separate courier-only action panel renders "Start Delivery" or
  "Mark Delivered" depending on status.
- `resources/views/deliveries/index.blade.php` remains office-only
  (couriers cannot see the delivery listing).

### Factory (`database/factories/DeliveryFactory.php`)

- `scheduled()` state now creates and attaches an active courier user
  via `User::factory()->courier()` unless the caller passes a
  pre-built courier via `->for($courier, 'courier')`.
- `inTransit()` state chains `scheduled()` then stamps
  `status = in_transit` and `dispatched_at = now('UTC')` as naked
  strings so SQLite `:memory:` stores them at second precision in
  UTC.
- `delivered()` state chains `inTransit()` then stamps
  `status = delivered` and `delivered_at = now('UTC')`.
- `cancelledFromInTransit()` state chains `inTransit()` then stamps
  the cancellation columns. Snapshot, receipt, courier_id,
  dispatched_at, distance, and fee remain populated.

## Tests

New unit suites:

- `tests/Unit/Domain/Delivery/DeliveryDispatcherTest.php` (8 tests).
- `tests/Unit/Domain/Delivery/DeliveryCompleterTest.php` (8 tests).

Extended unit suites:

- `DeliverySchedulerTest`: courier-required, invalid-role,
  inactive-courier, per-courier-cap-hit, and happy-path assertion
  that `courier_id` was written.
- `DeliveryCancellerTest`: eleven cases covering the full authorization
  matrix (draft/scheduled/in_transit x owner/staff/assigned
  courier/other courier) and preservation of all snapshot columns.
- `DeliveryStatusTest`: transition matrix rows for
  `scheduled -> in_transit` and `in_transit -> delivered`; terminal
  rows unchanged.

New feature suites:

- `tests/Feature/Delivery/DeliveryCourierAssignmentTest.php` (7 tests):
  courier_id captured at schedule, invalid role rejected, inactive
  courier rejected, per-courier cap enforced.
- `tests/Feature/Delivery/DeliveryFeePrivacyForCourierTest.php`
  (4 tests): courier `show` hides Pricing card and any fee/distance
  string; owner and staff still see them; dashboard has no fee.
- `tests/Feature/Delivery/DeliveryCourierDashboardTest.php` (7 tests):
  empty-state string `No active delivery`; other couriers' rows
  hidden; `scheduled` shows only Start Delivery; `in_transit` shows
  only Mark Delivered; terminals excluded; non-couriers 403; status
  pill.
- `tests/Feature/Delivery/DeliveryRouteAccessMatrixTest.php` (10 tests):
  guest to `/login`; owner and staff 403 on courier-only actions;
  inactive courier to `/login`; assigned courier success redirects to
  `route('deliveries.show', $delivery)` with a `status` flash.

## Verification

- `php artisan test`: **323 passed / 877 assertions** (was 271 / 720;
  +52 tests, +157 assertions).
- `php artisan route:list`: exactly 10 delivery routes; courier
  dashboard route named `courier.dashboard`.
- `npm run build`: successful.
- `composer audit`: 0 vulnerabilities.
- `npm audit`: 0 vulnerabilities.
- `git diff --check`: clean.
- MySQL migration applied at runtime:
  `2026_07_30_150000_add_courier_assignment_to_deliveries_table`.
- Runtime schema verified: `courier_id`, `dispatched_at`, and
  `delivered_at` present on `deliveries` table with correct types and
  FK constraint.

## Deliberately not implemented

- No telemetry ingestion, GPS coordinates on delivery rows, or
  live-position updates.
- No customer-facing surfaces or receipt-tracking authorization.
- No real-time maps, device provisioning, or firmware.
- No SMS notifications.
- No courier reassignment mid route.
- No new lifecycle state (AR-39: no "failed" state).
- Leaflet 1.9.4 is unchanged and unused by this packet.
- `.env` and `/home/ubuntu/GPS-server` untouched.

## Traceability

| Requirement    | Approved row | Implementing artifact                          |
| -------------- | ------------ | ---------------------------------------------- |
| DEL-FR-037     | AR-34        | `config/delivery.php:54`, per-courier cap      |
| DEL-FR-038     | AR-37        | `DeliveryScheduler` courier assertions         |
| DEL-FR-039     | AR-35        | `DeliveryDispatcher`, `DeliveryCompleter`      |
| DEL-FR-040     | AR-38 rev.   | `DeliveryCanceller` extended matrix            |
| DEL-FR-041     | AR-36        | `DashboardController::courier`, courier view   |
| DEL-FR-042     | AR-40        | Fee-privacy branch in `show.blade.php`         |
| DEL-FR-043     | AR-41        | `routes/web.php` (10 delivery routes)          |
| DEL-FR-044     | AR-39        | State enum unchanged (no failed state)         |
| DEL-FR-045     | AR-34        | `DELIVERY_MAX_CONCURRENT_PER_COURIER` env      |

