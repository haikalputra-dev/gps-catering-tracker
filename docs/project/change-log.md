# Change Log

Chronological, human-readable summary of application-visible changes.
For code diffs see `git log`. For rationale see the decision log and
the ADRs.

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
