# Change Log

Chronological, human-readable summary of application-visible changes.
For code diffs see `git log`. For rationale see the decision log and
the ADRs.

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
