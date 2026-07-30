# Identity and Access Requirements

Requirement identifiers for the identity slice introduced in Packet 04.
Each requirement is traced to the artefact that implements or verifies
it.

## Functional (IAM-FR)

| ID | Requirement | Implementation | Test |
|----|-------------|----------------|------|
| IAM-FR-001 | The system stores a role for every user. | migration `2026_07_30_103737_add_role_and_status_to_users_table` | `LoginTest` (factories) |
| IAM-FR-002 | Roles are exactly `owner`, `staff`, `courier`. | `app/Domain/Identity/UserRole.php` | `LoginTest`, `DashboardAccessTest` |
| IAM-FR-003 | Users can be flagged active/inactive. | migration + `User` cast | `ActiveAccountMiddlewareTest` |
| IAM-FR-004 | Users can log in with email and password. | `LoginRequest`, `AuthenticatedSessionController::store` | `LoginTest` |
| IAM-FR-005 | Users can log out. | `AuthenticatedSessionController::destroy` | `LoginTest::test_logout_ends_authentication` |
| IAM-FR-006 | Root `/` redirects guests to `/login`. | `routes/web.php` | `ExampleTest::test_root_redirects_guests_to_login` |
| IAM-FR-007 | Root `/` redirects authenticated users to `/dashboard`. | `routes/web.php` | manual/route list |
| IAM-FR-008 | `/dashboard` dispatches by role. | `DashboardController::index` | `DashboardAccessTest` |
| IAM-FR-009 | Role-specific dashboards exist. | `DashboardController::owner/staff/courier` + views | `DashboardAccessTest` |
| IAM-FR-010 | Owner can list staff and couriers. | `Owner\UserController::index` | `UserManagementTest::test_owner_can_list_staff_and_courier_accounts` |
| IAM-FR-011 | Owner can create staff and courier accounts. | `Owner\UserController::store` + `StoreUserRequest` | `UserManagementTest::test_owner_can_create_*` |
| IAM-FR-012 | Owner can edit staff and courier accounts. | `Owner\UserController::update` + `UpdateUserRequest` | `UserManagementUpdateTest::test_owner_can_update_staff_account` |
| IAM-FR-013 | Owner can toggle active state. | `Owner\UserController::update` | `UserManagementUpdateTest::test_owner_can_deactivate_*` |
| IAM-FR-014 | Owner can switch between staff and courier. | `Owner\UserController::update` | `UserManagementUpdateTest::test_owner_can_switch_staff_and_courier` |
| IAM-FR-015 | Owner can reset a staff/courier password. | `Owner\UserController::update` | `UserManagementUpdateTest::test_supplied_update_password_changes_password` |
| IAM-FR-016 | Blank password on edit keeps the existing password. | `Owner\UserController::update` | `UserManagementUpdateTest::test_empty_update_password_preserves_existing_password` |
| IAM-FR-017 | Initial owner is created via Artisan command. | `App\Console\Commands\CreateOwnerCommand` | `CreateOwnerCommandTest` |
| IAM-FR-018 | Command never accepts password as CLI input. | `CreateOwnerCommand::$signature` | `CreateOwnerCommandTest::test_password_is_not_part_of_command_signature` |
| IAM-FR-019 | Email is normalised (trim + lowercase) everywhere. | `LoginRequest::prepareForValidation`, request classes, command | `CreateOwnerCommandTest::test_command_lowercases_and_trims_email` |
| IAM-FR-020 | Owner navigation exposes user management. | `resources/views/layouts/app.blade.php` | manual smoke via feature tests |

## Security (IAM-SEC)

| ID | Requirement | Implementation | Test |
|----|-------------|----------------|------|
| IAM-SEC-001 | Passwords are hashed. | `User::casts()['password' => 'hashed']`, `Hash::make` | `CreateOwnerCommandTest::test_command_creates_active_owner_with_hashed_password` |
| IAM-SEC-002 | Failed logins are rate-limited. | `LoginRequest::ensureIsNotRateLimited` | (see AC-04-09 note in report) |
| IAM-SEC-003 | Successful login clears the failed-attempt limiter. | `LoginRequest::authenticate` | login tests are green with limiter reset in setUp |
| IAM-SEC-004 | Session is regenerated on login. | `AuthenticatedSessionController::store` | `LoginTest::test_successful_login_regenerates_session_id` |
| IAM-SEC-005 | Session is invalidated on logout. | `AuthenticatedSessionController::destroy` | `LoginTest::test_logout_ends_authentication` |
| IAM-SEC-006 | CSRF token is regenerated on logout. | `AuthenticatedSessionController::destroy` | code review |
| IAM-SEC-007 | Login errors are generic. | `LoginRequest::authenticate` uses `auth.failed` | `LoginTest::test_unknown_email_produces_same_generic_error` |
| IAM-SEC-008 | Inactive users cannot authenticate. | `LoginRequest::authenticate` includes `is_active=true` | `LoginTest::test_inactive_account_cannot_log_in` |
| IAM-SEC-009 | Deactivated users are dropped on next request. | `EnsureUserIsActive` | `ActiveAccountMiddlewareTest::test_user_deactivated_after_login_is_logged_out_on_next_request` |
| IAM-SEC-010 | Owner role can never be supplied through form input. | `UserRole::manageableRoles`, `StoreUserRequest`, `UpdateUserRequest`, `guardManageable` | `UserManagementTest::test_owner_cannot_submit_role_owner`, `UserManagementUpdateTest::test_owner_cannot_be_edited_through_crafted_request` |

## Acceptance (IAM-AC)

Mirrors the acceptance-criteria block in the packet.

| ID | Requirement | Verification |
|----|-------------|--------------|
| IAM-AC-001 | UserRole contains exactly owner, staff, courier. | `UserRole::cases()` inspection |
| IAM-AC-002 | Users table has role, phone, is_active. | migration + `migrate:status` |
| IAM-AC-003 | Role casts identically on SQLite and MySQL. | `RefreshDatabase` tests + MySQL migration |
| IAM-AC-004 | Factory supports owner/staff/courier/inactive states. | `UserFactory` used by every test |
| IAM-AC-005 | Active users can log in. | `LoginTest::test_active_*_can_log_in` |
| IAM-AC-006 | Inactive users cannot log in. | `LoginTest::test_inactive_account_cannot_log_in` |
| IAM-AC-007 | Login regenerates the session. | `LoginTest::test_successful_login_regenerates_session_id` |
| IAM-AC-008 | Logout invalidates session and rotates CSRF. | `AuthenticatedSessionController::destroy` + logout test |
| IAM-AC-009 | Failed logins are rate-limited. | `LoginRequest::MAX_ATTEMPTS` = 5 (see report) |
| IAM-AC-010 | Login failure does not reveal whether email exists. | `LoginTest::test_wrong_password_produces_generic_error` == `LoginTest::test_unknown_email_produces_same_generic_error` |
| IAM-AC-011 | No registration/forgot-password/remember-me. | `routes/web.php`, `resources/views/auth/login.blade.php` |
| IAM-AC-012 | Each role routes to its dashboard. | `DashboardAccessTest` |
| IAM-AC-013 | Cross-role dashboard access returns 403. | `DashboardAccessTest` |
| IAM-AC-014 | Deactivated users logged out on next request. | `ActiveAccountMiddlewareTest` |
| IAM-AC-015 | Owner-only routes reject staff and courier. | `DashboardAccessTest::test_staff_receives_403_*`, `..._courier_receives_403_*` |
| IAM-AC-016 | Owner can create staff and courier. | `UserManagementTest::test_owner_can_create_*` |
| IAM-AC-017 | Owner cannot create an owner through the web. | `UserManagementTest::test_owner_cannot_submit_role_owner` |
| IAM-AC-018 | Owner can edit and deactivate staff/courier. | `UserManagementUpdateTest::test_owner_can_update_staff_account`, `..._deactivate_*` |
| IAM-AC-019 | Owner cannot manage owner accounts. | `UserManagementUpdateTest::test_owner_cannot_be_edited_through_crafted_request` |
| IAM-AC-020 | No permanent delete route. | `UserManagementUpdateTest::test_no_account_delete_route_exists` |
