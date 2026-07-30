# ADR-007 - Role-Based Session Authentication

- Status: Accepted
- Date: 2026-07-30
- Packet: 04

## Context

Packet 04 introduces the identity slice for the GPS catering tracker.
Three account types are required by the operational model:

- Owner: business administrator (there is only one, or a very small
  handful, per deployment).
- Staff: kitchen and dispatch personnel.
- Courier: delivery driver.

Customer authentication (receipt number + phone digit lookup) is
deliberately out of scope for this packet.

Constraints inherited from prior decisions and the packet brief:

- Laravel 13 default `web` guard, session-based, database session
  driver already provisioned in Packet 03.
- Single-server, LAN-scoped MySQL deployment. No external identity
  providers, no SSO, no mobile app yet.
- Owner controls staff/courier lifecycle from a web UI.
- No public registration, forgot-password, remember-me, or email
  verification in this packet.
- Passwords never appear in `.env`, seeders, or CLI arguments.
- Tests must pass on SQLite `:memory:` and behaviour must match on
  MySQL.

## Options considered

### Option A - Custom role column on `users`, session-only auth (chosen)

Add `role`, `phone`, `is_active` columns directly to `users`. Guard
access with:

- `LoginRequest` for authentication (throttle + generic errors +
  `is_active = true` constraint).
- `EnsureUserIsActive` middleware for post-login enforcement.
- `RequireRole` middleware for role gating.
- `Owner\UserController` for management, restricted through
  `UserRole::manageableRoles()` and `guardManageable()`.

Pros:

- One column, one index, no join.
- No external package to keep in step with Laravel major upgrades.
- Owner-only, staff-only, courier-only routes are trivial to express.
- Fits the small role catalogue that will not change often.

Cons:

- Users cannot hold multiple roles at once. Acceptable: the domain
  does not require it.
- Fine-grained permissions require code changes, not data changes.
  Acceptable for the current feature set.

### Option B - Spatie Laravel-Permission package

Adds `roles`, `permissions`, `model_has_roles`, `model_has_permissions`,
`role_has_permissions` tables and a Blade/middleware DSL.

Pros:

- Well-known, actively maintained.
- Multiple roles and fine-grained permissions out of the box.

Cons:

- Five extra tables and a package upgrade cadence for a project that
  has three fixed roles.
- Larger blast radius on Laravel upgrades.
- Encourages permission sprawl before the domain needs it.
- Adds one more thing to reason about when reviewing the auth model.

### Option C - Separate guards per role

Distinct `owner`, `staff`, `courier` guards backed by three tables (or
one polymorphic table).

Pros:

- Physically separates account types.

Cons:

- Three login endpoints or a dispatching layer on top of them.
- Duplicated password reset / active-account plumbing per guard.
- Cross-role edits (owner deactivating a staff member) require
  cross-guard code.
- Overkill for the current single-tenant deployment.

## Decision

Adopt Option A: a single `users` table with a `role` string column, a
`phone` string column and an `is_active` boolean column. Access control
is expressed through named enum-driven middleware, and staff/courier
management lives under `Owner\UserController`. Passwords are hashed by
Laravel's default hasher. The initial owner is created only via
`app:create-owner` with hidden password prompts.

## Consequences

Positive:

- Minimal schema change, minimal moving parts.
- Straight-line upgrade path for future Laravel releases.
- Clear, testable boundaries: every capability is covered by a feature
  test in `tests/Feature/Auth/` or `tests/Feature/Owner/`.
- No secret material leaves the terminal during owner creation.

Negative or watch-outs:

- Adding a fourth role (e.g., dispatcher) requires an enum change and
  a review of `manageableRoles()`. This is intentional, so a new role
  cannot be introduced by data migration alone.
- Fine-grained permissions (per-route toggles per user) would need a
  future ADR and probably Option B revisited at that time.
- No self-service password reset yet. Owners must reset staff/courier
  passwords through the edit form; the initial owner password reset
  is not solvable through the web UI and requires a new run of
  `app:create-owner` on the target host.

## Related documents

- `docs/authentication/role-access.md`
- `docs/authentication/initial-owner-command.md`
- `docs/requirements/identity-access-requirements.md`
- `docs/task-reports/task-packet-04-report.md`
