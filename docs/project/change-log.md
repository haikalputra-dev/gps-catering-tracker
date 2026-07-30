# Change Log

Chronological, human-readable summary of application-visible changes.
For code diffs see `git log`. For rationale see the decision log and
the ADRs.

## 2026-07-30 - Packet 10 - Customer delivery tracking

### Added

- Public tracking surface at `/track`, backed by
  `App\Http\Controllers\TrackingController` (`form`, `authenticate`,
  `status`, `signOut`).
- `App\Domain\Tracking\TrackingAuthenticator`: stateless service that
  resolves a `Delivery` from receipt number + phone-last-4. Normalizes
  receipts (trim, uppercase, strip separators, re-hyphenate the
  15-character canonical form) and rejects draft rows. Uses
  `hash_equals` on the last four snapshot digits for constant-time
  comparison (AR-42 revised).
- `App\Http\Requests\Tracking\TrackingAuthenticateRequest`: collapses
  every rule failure across both fields into a single generic error
  keyed `form`, so failure copy cannot leak which factor was wrong.
- `resources/views/layouts/public.blade.php`: no-auth-header layout
  used only for tracking pages, plus `resources/views/tracking/form
  .blade.php` and `resources/views/tracking/status.blade.php`.
- Four routes registered in `routes/web.php`:
  `GET /track` (tracking.form), `POST /track` with `throttle:10,15`
  (tracking.authenticate), `GET /track/status` (tracking.status),
  `POST /track/sign-out` (tracking.signOut).

### Behavior notes

- No customer accounts, SMS, OTP, signed URLs, WebSockets, polling,
  live map, or telemetry are introduced. The status page renders a
  timeline (scheduled / dispatched / delivered / cancelled), the
  kitchen and customer snapshots, distance and fee, and - only when
  the delivery is `in_transit` - the assigned courier's name and
  phone.
- Session key `tracking.delivery_id` scopes the status page. The
  session id is regenerated on successful authentication to prevent
  fixation. `POST /track/sign-out` clears the key and regenerates the
  CSRF token.
- Draft deliveries are never trackable, even if a caller supplies a
  hypothetical receipt.

### Tests

- 51 new tests / 155 new assertions across
  `tests/Unit/Domain/Tracking/TrackingAuthenticatorTest.php`,
  `tests/Feature/Tracking/TrackingFormTest.php`,
  `tests/Feature/Tracking/TrackingAuthenticateTest.php`,
  `tests/Feature/Tracking/TrackingStatusTest.php`,
  `tests/Feature/Tracking/TrackingSignOutTest.php`, and
  `tests/Feature/Tracking/TrackingThrottleTest.php`.
- The Packet 07 guard test
  `DeliveryRouteTest::test_public_receipt_lookup_route_does_not_exist`
  was renamed to
  `test_only_the_tracking_route_exposes_receipt_lookup` and now
  enforces that `/track` is the single legitimate lookup surface
  while variants (`/tracking`, `/lookup`, `/api/track`, etc.) still
  404.

### Decisions

- Applies AR-42 (revised): tracking authentication uses receipt +
  phone-last-4 only, `hash_equals` comparison, generic error copy,
  session-scoped access, and `throttle:10,15` on the POST endpoint.
- Applies AR-43..AR-46 as recorded in the decision log.

## 2026-07-30 - Packet 09 - Courier assignment, dispatch, and completion

### Added

- Migration
  `2026_07_30_XXXXXX_add_courier_and_lifecycle_timestamps_to_deliveries_table`
  adding `courier_id bigint NULL` foreign key to `users` (index +
  fk),`dispatched_at timestamp NULL`, and `delivered_at timestamp
  NULL`.
- `App\Domain\Delivery\DeliveryDispatcher` (state check, actor
  identity, inactive-courier guard, atomic status + `dispatched_at`
  write) and `App\Domain\Delivery\DeliveryCompleter` (state check,
  actor identity, atomic status + `delivered_at` write, monotonic
  ordering guarantee).
- Extended `App\Domain\Delivery\DeliveryScheduler` with
  `assertCourier()` and `assertCourierCapacity()` (per-courier cap
  from `config('delivery.max_per_courier_active')`).
- Extended `App\Domain\Delivery\DeliveryCanceller` to permit
  cancellation from `in_transit` with per-actor authorization
  (owner/staff any non-terminal; assigned courier own `in_transit`
  only).
- New typed exceptions under `App\Domain\Delivery\Exceptions`:
  `MissingCourierException`, `CourierNotCourierRoleException`,
  `InactiveCourierException`, `CourierConcurrencyLimitReachedException`,
  `NotDispatchableStateException`, `NotCompletableStateException`,
  `NotAssignedCourierException`, `NotAuthorizedToCancelException`.
- Two new HTTP endpoints on the delivery resource:
  `POST /deliveries/{delivery}/dispatch` (name
  `deliveries.dispatch`, role `courier`) and
  `POST /deliveries/{delivery}/mark-delivered` (name
  `deliveries.mark-delivered`, role `courier`).
- Courier dashboard at `GET /courier/dashboard` (role `courier`)
  scoped to the acting courier's active deliveries only, with
  state-appropriate action buttons (`Start Delivery` /
  `Mark Delivered`) and an empty-state.
- `role:` middleware extended to accept comma-separated role lists
  (`role:owner,staff,courier`) for the shared show/cancel group.
- `UserFactory` states `courier()` and `inactive()` and
  `DeliveryFactory` states `inTransit()`, `delivered()`,
  `cancelledFromInTransit()`.
- `docs/deliveries/courier-assignment.md`,
  `docs/deliveries/dispatch-and-completion.md`,
  `docs/deliveries/mid-route-cancellation.md`,
  `docs/deliveries/courier-visibility-and-fee-privacy.md`,
  `docs/decisions/ADR-014-courier-assignment-and-per-courier-limit.md`,
  `docs/decisions/ADR-015-dispatch-and-completion-via-manual-taps.md`.

### Changed

- `deliveries` show page: Pricing card, Distance readout, and
  formatted fee wrapped in an office-only Blade branch. Assigned
  couriers see the row without pricing.
- `deliveries` cancel FormRequest: `authorize()` covers the AR-38
  revised matrix (owner/staff any non-terminal; assigned courier own
  `in_transit` only). `failedAuthorization()` throws
  `HttpResponseException` with a redirect and session error under
  `status`.
- `deliveries` show controller: unassigned couriers hitting the show
  URL are redirected to `courier.dashboard` with a session error
  rather than 403 (avoids leaking existence of the delivery).
- `.env.example` gained `DELIVERY_MAX_CONCURRENT_PER_COURIER=1`.
- `config/delivery.php` gained `max_concurrent_per_courier` key.
- `docs/deliveries/delivery-state-machine.md`,
  `docs/deliveries/concurrency-limit.md`, and
  `docs/requirements/delivery-requirements.md` updated for the new
  transitions, per-courier cap, and Packet 09 requirements
  (DEL-FR-037..045, DEL-AC-056..067).

### Tests

- New feature tests:
  `DeliveryCourierAssignmentTest` (7),
  `DeliveryFeePrivacyForCourierTest` (4),
  `DeliveryCourierDashboardTest` (7),
  `DeliveryRouteAccessMatrixTest` (10).
- New unit tests:
  `DeliveryDispatcherTest` (8),
  `DeliveryCompleterTest` (8).
- Updated: `DeliveryStatusTest` (in_transit→cancelled now valid),
  `DeliveryRouteTest` (10-route baseline + alias 404s),
  `DeliveryAuthorizationTest` (redirect semantics for unassigned
  courier and cancel matrix), `DeliveryStateMachineTest`,
  `DeliveryPricingTest`, `DeliveryConcurrencyLimitTest`
  (`draft()`/`draftAt()` seed a courier), and
  `DeliveryCancellerTest` (mid-route cancellation matrix).
- Full suite: 323 tests, 877 assertions, all passing.

## 2026-07-30 - Packet 08 - Delivery distance and fee

### Added

- Migration
  `2026_07_30_141932_add_distance_and_fee_to_deliveries_table` adding
  `distance_km decimal(8,3) NULL` after `customer_longitude` and
  `fee_rupiah unsigned int NULL` after `distance_km`.
- `App\Domain\Delivery\DistanceCalculator` (Haversine, IUGG mean
  radius `6371.0088` km on `EARTH_RADIUS_KM`, arg validation for
  lat/lng, clamp for antipodal).
- `App\Domain\Delivery\PricingCalculator` (config-driven, half-up
  divide/multiply rounding, floor). Reads `config('pricing.*')` on
  every call.
- `config/pricing.php` with three keys (`minimum_fee_rupiah`,
  `rate_per_km_rupiah`, `fee_rounding_step_rupiah`) and matching
  `PRICING_*` entries in `.env.example`.
- `docs/deliveries/pricing-and-distance.md` and
  `docs/decisions/ADR-013-haversine-and-fee-formula.md`.
- Unit tests
  (`tests/Unit/Domain/Delivery/DistanceCalculatorTest.php`,
  `PricingCalculatorTest.php`) and feature test
  (`tests/Feature/Delivery/DeliveryPricingTest.php`) totalling 36
  new tests, 67 new assertions.

### Changed

- `App\Models\Delivery`: `distance_km`, `fee_rupiah` added to
  `$fillable`; casts `decimal:3` and `integer` added.
- `App\Domain\Delivery\DeliveryScheduler`: constructor injects
  `DistanceCalculator` and `PricingCalculator` alongside
  `ReceiptNumberGenerator`. Distance and fee are computed after the
  concurrency check and written in the same `forceFill` that
  captures the snapshot and receipt.
- `DeliveryFactory` `scheduled` state (inherited by
  `cancelledFromScheduled`): populates `distance_km` and
  `fee_rupiah` via container-resolved calculators.
- `resources/views/deliveries/index.blade.php`: new Fee column.
- `resources/views/deliveries/show.blade.php`: new Pricing card
  (distance, fee, note that value is frozen at scheduling).
- `docs/project/decision-log.md`: AR-29..AR-33 appended (AR-29
  marked "Approved (revised)"); Packet 08 governance-audit note.
- `docs/requirements/delivery-requirements.md`: DEL-FR-031..036 and
  DEL-AC-041..055 sections plus traceability row.

### Not changed

- `.env` untouched.
- `/home/ubuntu/GPS-server` untouched.
- No new route, controller action, or FormRequest.
- Delivery state machine and route list are unchanged from Packet 07.
- No composer or npm package added or removed.
- Leaflet 1.9.4 unchanged; not used by this packet.

### Test result

- `php artisan test`: 271 passed, 720 assertions.

### Migration status

- MySQL:
  `2026_07_30_141932_add_distance_and_fee_to_deliveries_table`
  applied 2026-07-30.

## 2026-07-30 - Packet 07 - Delivery orders

### Added

- Migration `2026_07_30_062930_create_deliveries_table` creating the
  `deliveries` table with a five-state status column (`draft`,
  `scheduled`, `in_transit`, `delivered`, `cancelled`), unique
  nullable `receipt_number varchar(20)`, ten snapshot columns
  (five kitchen, five customer), audit columns for scheduling and
  cancellation, and FKs to `kitchens`, `customers`, and `users` with
  `restrictOnDelete`.
- `App\Domain\Delivery\DeliveryStatus` backed enum with helpers
  `isEditable`, `isActiveForConcurrency`, `isTerminal`, `label`,
  `canTransitionTo`, `activeCases`, `terminalCases`, `values`.
- `App\Domain\Delivery\ReceiptNumberGenerator` producing
  `DEL-YYYYMMDD-XXXX` receipts using `random_int` over a 30-character
  alphabet, with 10-retry uniqueness loop.
- `App\Domain\Delivery\DeliveryScheduler` transactional service
  performing `draft -> scheduled` with `lockForUpdate` on the
  delivery, kitchen, and customer rows, atomic snapshot capture,
  receipt issuance, and configurable concurrency cap enforcement.
- `App\Domain\Delivery\DeliveryCanceller` transactional service
  performing `draft/scheduled -> cancelled` with reason 3..255 chars,
  preserving receipt and snapshot data on scheduled origin.
- Seven typed exceptions under `app/Domain/Delivery/Exceptions/`:
  `NotSchedulableStateException`, `MissingSchedulingFieldsException`,
  `InactiveKitchenException`, `InactiveCustomerException`,
  `ConcurrencyLimitReachedException`, `NotCancellableStateException`,
  `CancellationReasonRequiredException`.
- `App\Models\Delivery` with `DeliveryStatus` cast, decimal-7
  coordinate casts, `kitchen`/`customer`/`createdBy`/`scheduledBy`/
  `cancelledBy` relations, and `draft`/`scheduled`/`active`/`terminal`
  scopes.
- Four FormRequests under `App\Http\Requests\Delivery`:
  `StoreDeliveryRequest`, `UpdateDeliveryRequest` (draft-only
  `authorize()`), `ScheduleDeliveryRequest` (draft-only + fields
  present + future), `CancelDeliveryRequest` (reason 3..255).
- `App\Http\Controllers\DeliveryController` with 8 actions:
  `index`, `create`, `store`, `show`, `edit`, `update`, `schedule`,
  `cancel`. Exception-to-session-error mapping for scheduling and
  cancellation.
- Eight `deliveries.*` routes under `auth`, `active`,
  `role:owner,staff` middleware. POST for `schedule` and `cancel`.
  No DELETE, no API, no tracking route.
- Blade views under `resources/views/deliveries/` (`index`,
  `create`, `show`, `edit`) with partials `_form`, `_status_badge`,
  `_audit`, `_action_buttons`. Show and index branch on status to
  render live vs snapshot data.
- `config/delivery.php` with five configurable keys and matching
  `DELIVERY_*` entries in `.env.example`.
- `DeliveryFactory` with `scheduled`, `cancelledFromDraft`,
  `cancelledFromScheduled` states (Faker only).
- Unit tests (`tests/Unit/Domain/Delivery/`) and feature tests
  (`tests/Feature/Delivery/`) totalling 82 new tests, 256 new
  assertions.
- Documentation: `docs/deliveries/*`,
  `docs/requirements/delivery-requirements.md`,
  `docs/decisions/ADR-{010,011,012}*.md`,
  `docs/task-reports/task-packet-07-report.md`.

### Changed

- `routes/web.php`: `DeliveryController` import + 8-route group
  under `role:owner,staff`.
- `resources/views/layouts/app.blade.php`: Deliveries nav link for
  owner and staff only.
- `.env.example`: five new `DELIVERY_*` keys appended.
- `docs/project/decision-log.md`: AR-23..AR-28 appended with AR-27
  revision note and Packet 07 governance-audit note.
- README, `docs/architecture/project-structure.md`, risk register,
  and progress documents surgically updated.

### Not changed

- `.env` untouched.
- `DatabaseSeeder` untouched.
- `/home/ubuntu/GPS-server` untouched.
- No composer/npm package added or removed.
- No delete route, no soft-delete column.

### Test result

- `php artisan test`: 235 passed, 653 assertions.

### Migration status

- MySQL: `2026_07_30_062930_create_deliveries_table` applied
  2026-07-30.

## 2026-07-30 - Packet 06 - Customer management

### Added

- Migration `2026_07_30_060000_create_customers_table` with fields
  `name` (150), `phone` (25, unique), `address` (text),
  `latitude` (`decimal(10,7)`), `longitude` (`decimal(10,7)`),
  `notes` (text, nullable), `is_active` (bool, indexed), timestamps.
- `App\Models\Customer` with `HasFactory`, `active()` scope, and
  `decimal:7` casts on latitude/longitude.
- `App\Domain\Customer\CustomerPhone` normalizer/validator
  (`normalize`, `isValid`, `fromInput`) with constants
  `MIN_DIGITS=9` and `MAX_DIGITS=15`, covered by
  `tests/Unit/Domain/Customer/CustomerPhoneTest.php`.
- `StoreCustomerRequest` and `UpdateCustomerRequest` under
  `App\Http\Requests\Customer` with `prepareForValidation`
  normalization and unique-with-ignore semantics.
- `App\Http\Controllers\CustomerController` with actions `index`,
  `create`, `store`, `edit`, `update` only. No destroy action.
- `database/factories/CustomerFactory.php` with default active
  state and an `inactive()` state; Faker-only values.
- Leaflet 1.9.4 reused (already installed) via new
  `resources/js/customer-map.js` imported from
  `resources/js/app.js`. No new `MAP_*` env variables; existing
  `config/map.php` values are shared.
- Blade views under `resources/views/customers/` (`index`,
  `create`, `edit`, `_form`) with the map picker, phone-masking
  index, and coordinate display.
- Feature and unit tests under `tests/Feature/Customer/` and
  `tests/Unit/Domain/Customer/` covering authorization,
  management, validation, and the route surface (55 new tests).

### Changed

- `routes/web.php` gained a `customers` route group under
  `auth`, `active`, `role:owner,staff` middleware with exactly five
  named endpoints. No `customers.destroy`.
- `resources/js/app.js` imports `./customer-map.js` in addition to
  the existing kitchen module.
- `resources/css/app.css` gained customer-scoped styles for the
  map container, coordinate display, instruction paragraph, phone
  mask, and address/notes textareas.
- `resources/views/layouts/app.blade.php` shows a "Customers" nav
  link to owner and staff users.
- Governance: `AR-22` recorded and approved in
  `docs/project/decision-log.md` before implementation.

### Migrations run

- MySQL: `2026_07_30_060000_create_customers_table` in batch 4,
  applied 2026-07-30.

## 2026-07-30 - Packet 05 - Kitchen management

### Added

- Migration `2026_07_30_042022_create_kitchens_table` with fields
  `code` (30, unique), `name` (150), `address` (text), `phone` (25,
  nullable), `latitude` (`decimal(10,7)`), `longitude` (`decimal(10,7)`),
  `is_active` (bool, indexed), timestamps.
- `App\Models\Kitchen` with `HasFactory`, `active()` scope, and
  `decimal:7` casts on latitude/longitude.
- `App\Domain\Kitchen\KitchenCode` value normalizer (`normalize`,
  `isValid`, `fromInput`) covered by
  `tests/Unit/Domain/Kitchen/KitchenCodeTest.php`.
- `StoreKitchenRequest` and `UpdateKitchenRequest` under
  `App\Http\Requests\Kitchen` with normalization in
  `prepareForValidation()` and unique-code rule that ignores self on
  update.
- `App\Http\Controllers\KitchenController` with actions `index`,
  `create`, `store`, `edit`, `update` only. No destroy action.
- `database/factories/KitchenFactory.php` with default active state
  and an `inactive()` state.
- Leaflet 1.9.4 pinned via `npm install leaflet --save-exact` and
  wired through Vite via `resources/js/kitchen-map.js` imported from
  `resources/js/app.js`.
- `config/map.php` and `.env.example` entries for `MAP_*` variables
  (default center, zoom, selection zoom, tile URL, attribution, tile
  max zoom).
- Blade views under `resources/views/kitchens/` (`index`, `create`,
  `edit`, `_form`) with the map picker and coordinate display.
- Feature and unit tests under `tests/Feature/Kitchen/` and
  `tests/Unit/Domain/Kitchen/` covering authorization, management,
  lifecycle, validation, and the route surface (57 new tests).

### Changed

- `routes/web.php`: added a kitchen route group under
  `auth`, `active`, `role:owner,staff`.
- `resources/views/layouts/app.blade.php`: added a "Kitchens" nav link
  visible only when the current user is owner or staff.
- `resources/css/app.css`: added `#kitchen-map`,
  `#kitchen-coordinate-display`, `.kitchen-map-instruction`.
- `resources/js/app.js`: imports `./kitchen-map.js`.
- `docs/project/decision-log.md`: AR-16..AR-20 marked Void with audit
  note; AR-21 added and approved.

### Not changed

- `.env` untouched.
- `DatabaseSeeder` untouched; do not run `db:seed`.
- `/home/ubuntu/GPS-server` untouched.
- No delete route, no soft-delete column, no destroy action.
- No composer package added or removed.

### Test result

- `php artisan test`: 98 passed, 273 assertions.

### Migration status

- MySQL: `2026_07_30_042022_create_kitchens_table` in batch 3, applied
  2026-07-30.

## 2026-07-30 - Packet 04 - Role-based session authentication

### Added

- `UserRole` enum with `owner`, `staff`, `courier` values, plus helpers
  `label()`, `manageableRoles()`, `manageableValues()`.
- Migration `2026_07_30_103737_add_role_and_status_to_users_table` adds
  `role`, `phone`, `is_active` columns to the `users` table (both role
  and `is_active` indexed).
- Factory states `owner`, `staff`, `courier`, `inactive` on
  `UserFactory`.
- Artisan command `app:create-owner`
  (`App\Console\Commands\CreateOwnerCommand`) for provisioning the
  initial owner via hidden password prompts.
- Custom `Auth\AuthenticatedSessionController` + `LoginRequest`
  handling email normalisation, rate limiting (5 attempts / 60s per
  email+IP), active-account enforcement and session regeneration.
- Middleware `EnsureUserIsActive` (alias `active`) and `RequireRole`
  (alias `role`) registered in `bootstrap/app.php`.
- Dashboard dispatcher (`DashboardController`) plus owner, staff and
  courier dashboards.
- Owner user-management screens: list, create, edit
  (`Owner\UserController` + `StoreUserRequest` + `UpdateUserRequest`).
  No delete route.
- Blade layout, login screen, three role dashboards and owner user
  management views, framework-free (inline CSS, no Tailwind).
- Feature test suites:
  - `tests/Feature/Auth/LoginTest.php`
  - `tests/Feature/Auth/DashboardAccessTest.php`
  - `tests/Feature/Auth/ActiveAccountMiddlewareTest.php`
  - `tests/Feature/Owner/UserManagementTest.php`
  - `tests/Feature/Owner/UserManagementUpdateTest.php`
  - `tests/Feature/Console/CreateOwnerCommandTest.php`

### Changed

- `App\Models\User`: fillable list adds `role`, `phone`, `is_active`;
  casts include `role => UserRole::class` and `is_active => bool`; new
  `isOwner()`, `isStaff()`, `isCourier()` helpers.
- `routes/web.php`: guest login pair, POST logout, dashboard hub, role
  dashboards, owner user routes; `/` redirects guests to `/login` and
  authenticated users to `/dashboard`.
- `bootstrap/app.php`: `active` and `role` middleware aliases
  registered.
- `tests/Feature/ExampleTest.php`: baseline test updated to assert
  `/` redirects guests to `/login`.

### Not changed

- No package additions or removals (no Spatie Permission, Fortify,
  Breeze, Jetstream or Sanctum).
- `.env` untouched.
- `DatabaseSeeder` untouched; do not run `db:seed`.
- `/home/ubuntu/GPS-server` untouched.
- MySQL runtime credentials from Packet 03 are unchanged.

### Test result

- `php artisan test`: 45 passed, 132 assertions.

### Migration status

- MySQL: `2026_07_30_103737_add_role_and_status_to_users_table` in
  batch 2, applied 2026-07-30.

## 2026-07-30 - Packet 03 - MySQL runtime

See `docs/database-integration-report.md`.

## 2026-07-30 - Packet 02 - Bootstrap

See `docs/bootstrap-report.md`.
