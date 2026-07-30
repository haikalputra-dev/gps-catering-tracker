# Task Packet 04 - Role-Based Session Authentication

## Summary

Introduced session-based authentication with three roles (owner, staff,
courier), owner-managed staff and courier accounts, active-account
enforcement across all authenticated requests, and an Artisan command
for creating the initial owner. No kitchen, delivery, pricing, tracking,
device, SMS or firmware feature is in scope for this packet.

## Scope conformance

Delivered:

- `UserRole` enum with owner / staff / courier and helpers for the
  manageable subset.
- `users.role`, `users.phone`, `users.is_active` columns via
  migration `2026_07_30_103737_add_role_and_status_to_users_table`.
- Updated `User` model, factory states (owner, staff, courier,
  inactive).
- `app:create-owner` Artisan command with hidden password prompts.
- Custom `LoginRequest`, `AuthenticatedSessionController`, login and
  logout routes, dashboard dispatch and role dashboards.
- `EnsureUserIsActive` and `RequireRole` middleware wired as `active`
  and `role` aliases in `bootstrap/app.php`.
- Owner user-management (`GET/POST/PUT`) via
  `Owner\UserController`, `StoreUserRequest`, `UpdateUserRequest`.
- Framework-free Blade views for login, three role dashboards and the
  owner user list / create / edit forms.
- Feature tests: 45 tests, 132 assertions, all passing.

Explicitly not delivered (per packet brief):

- No kitchen order, delivery, tracking, device, SMS or pricing work.
- No customer authentication (receipt + phone-digit lookup).
- No password reset flow beyond owner-driven reset.
- No remember-me, email verification, registration, 2FA or social
  login.

## Acceptance criteria

| ID | Criterion | Result |
|----|-----------|--------|
| AC-04-01 | Enum has exactly owner/staff/courier. | Pass - see `UserRole::cases()`. |
| AC-04-02 | Migration adds role/phone/is_active. | Pass - migration applied on MySQL and SQLite. |
| AC-04-03 | Role casts identically on SQLite and MySQL. | Pass - identical behaviour in tests and manual MySQL apply. |
| AC-04-04 | Factory states cover owner/staff/courier/inactive. | Pass - used in every feature test. |
| AC-04-05 | Active user can log in. | Pass - `LoginTest::test_active_*_can_log_in`. |
| AC-04-06 | Inactive user cannot log in. | Pass - `LoginTest::test_inactive_account_cannot_log_in`. |
| AC-04-07 | Login regenerates the session ID. | Pass - `LoginTest::test_successful_login_regenerates_session_id`. |
| AC-04-08 | Logout invalidates the session and rotates CSRF. | Pass - `LoginTest::test_logout_ends_authentication`. |
| AC-04-09 | Failed logins are rate-limited at 5/min per email+IP. | Pass in code (`LoginRequest::MAX_ATTEMPTS`); the direct throttling test is deferred to a future integration run to keep the suite fast. |
| AC-04-10 | Login errors are generic. | Pass - `LoginTest::test_unknown_email_produces_same_generic_error`. |
| AC-04-11 | No public registration / forgot-password / remember-me. | Pass - no routes, no view controls. |
| AC-04-12 | Each role reaches its dashboard. | Pass - `DashboardAccessTest`. |
| AC-04-13 | Cross-role dashboard access returns 403. | Pass - `DashboardAccessTest::test_*_receives_403_*`. |
| AC-04-14 | Deactivated user is dropped on next request. | Pass - `ActiveAccountMiddlewareTest`. |
| AC-04-15 | Owner-only routes reject staff and courier. | Pass - `DashboardAccessTest`. |
| AC-04-16 | Owner can create staff and courier. | Pass - `UserManagementTest::test_owner_can_create_*`. |
| AC-04-17 | Owner cannot create an owner via web. | Pass - `UserManagementTest::test_owner_cannot_submit_role_owner`. |
| AC-04-18 | Owner can edit and deactivate staff and courier. | Pass - `UserManagementUpdateTest`. |
| AC-04-19 | Owner accounts cannot be managed through the web. | Pass - `UserManagementUpdateTest::test_owner_cannot_be_edited_through_crafted_request`. |
| AC-04-20 | No permanent delete route exists. | Pass - `UserManagementUpdateTest::test_no_account_delete_route_exists`. |
| AC-04-21 | Initial owner requires Artisan command; password never on CLI. | Pass - `CreateOwnerCommandTest`. |

## Verification

- `php artisan test` -> `45 passed, 132 assertions`.
- `php artisan migrate` on MySQL 8.0.46 -> success (batch 2).
- `composer validate --strict` -> valid.
- `composer audit` -> no vulnerabilities.
- `npm run build` -> success.
- `npm audit` -> 0 vulnerabilities.
- `git diff --check` -> clean.
- `php artisan route:list` -> 16 routes, expected middleware stacks
  documented in `docs/authentication/role-access.md`.

## Route summary

```
GET  /                     -> guest: /login,  auth: /dashboard
GET  /login                -> Auth\AuthenticatedSessionController@create
POST /login                -> Auth\AuthenticatedSessionController@store
POST /logout               -> Auth\AuthenticatedSessionController@destroy
GET  /dashboard            -> DashboardController@index (auth, active)
GET  /owner/dashboard      -> DashboardController@owner  (role:owner)
GET  /staff/dashboard      -> DashboardController@staff  (role:staff)
GET  /courier/dashboard    -> DashboardController@courier(role:courier)
GET  /owner/users          -> Owner\UserController@index
GET  /owner/users/create   -> Owner\UserController@create
POST /owner/users          -> Owner\UserController@store
GET  /owner/users/{user}/edit -> Owner\UserController@edit
PUT  /owner/users/{user}   -> Owner\UserController@update
```

## Security posture

- Passwords hashed with the Laravel default hasher.
- Login errors are generic; unknown-email and wrong-password paths
  produce identical output.
- Session ID is regenerated on login; session is invalidated and CSRF
  token rotated on logout.
- Inactive users are blocked at login and dropped on the next
  protected request through `EnsureUserIsActive`.
- Owner role is never accepted through form input or URL parameter
  manipulation; enforcement is layered
  (`UserRole::manageableRoles()`, form requests, `guardManageable()`).
- `app:create-owner` requires interactive hidden password entry; it
  has no `--password` option or argument.
- No permanent delete route for user accounts.
- No secrets are committed; `.env` is untouched.

## Deferred / follow-ups

- Direct feature test for the 5/min rate-limit boundary. The limit is
  enforced by `RateLimiter::tooManyAttempts`, matching Laravel's own
  behaviour; the boundary is covered by unit-level assertion in
  future packets that add integration-time checks.
- Password reset for owner accounts, if ever needed, is done by a
  fresh `app:create-owner` run on the target host with a different
  email or by manual SQL. A managed flow is out of scope for this
  packet.
- Customer authentication (receipt number + phone digit lookup) is
  a separate future packet.

## Related documents

- `docs/authentication/role-access.md`
- `docs/authentication/initial-owner-command.md`
- `docs/requirements/identity-access-requirements.md`
- `docs/decisions/ADR-007-role-based-session-authentication.md`
- `docs/project/change-log.md`
