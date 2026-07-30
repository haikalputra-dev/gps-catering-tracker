# Role-Based Access

Behaviour of the three account roles introduced in Packet 04.

## Roles

Stored values in `users.role`:

- `owner`
- `staff`
- `courier`

The application enum lives in `app/Domain/Identity/UserRole.php`. Storage
values must never change. Additional roles are out of scope for this
packet.

## What each role may do

### Owner

- Log in with email and password (must be active).
- Access the owner dashboard.
- Create staff and courier accounts.
- Edit staff and courier account details (name, email, phone, role
  between staff/courier, active status, optional new password).
- Deactivate or reactivate staff and courier accounts.
- Reset a staff or courier password by supplying a new one on the edit
  form.

### Staff

- Log in with email and password (must be active).
- Access the staff dashboard.

No kitchen, delivery, tracking, device or customer feature is available
yet.

### Courier

- Log in with email and password (must be active).
- Access the courier dashboard.

No delivery assignment, tracking or device feature is available yet.

### Customer

Customer authentication is not part of this packet. Receipt-number +
phone-digit tracking access will be introduced in a later packet.

## Owner web-management boundaries

The owner CANNOT do any of the following through the web UI:

- Create another owner account.
- Change an existing account into an owner.
- Edit an owner account.
- Deactivate an owner account.
- Delete accounts permanently.

These boundaries are enforced in three places:

- `UserRole::manageableRoles()` restricts the allowed target roles to
  `staff` and `courier`.
- Owner form requests reject any `role` value outside those two.
- `Owner\UserController::guardManageable()` returns HTTP 404 for any
  target whose stored role is not manageable, including crafted URLs
  pointing at owner accounts.

## Login and logout behaviour

- Route: `GET /login` (guests only), `POST /login`, `POST /logout`.
- Email is trimmed and lower-cased before authentication.
- Only active accounts (`is_active = true`) can complete login.
- Session ID is regenerated on successful login.
- Session is invalidated and CSRF token regenerated on logout.
- Failed login attempts are rate-limited: 5 attempts per minute per
  email + IP combination (see `LoginRequest::MAX_ATTEMPTS`).
- On successful login the limiter is cleared.
- Login errors are always generic: `auth.failed`. The response never
  reveals whether the email exists or whether the account is inactive.
- Remember-me is not implemented.
- Forgot-password is not implemented.
- Public registration is not implemented.

## Active-account enforcement

Middleware alias `active` (`EnsureUserIsActive`) runs on every
authenticated route. If a user is deactivated after logging in:

- They are logged out on their next protected request.
- The session is invalidated and the CSRF token regenerated.
- They are redirected to `/login` with the generic status message
  "Your account is not available." No internal state is exposed.

## Role dispatching

- `GET /dashboard` requires `auth` and `active`, then redirects the user
  based on their role:
  - Owner -> `/owner/dashboard`.
  - Staff -> `/staff/dashboard`.
  - Courier -> `/courier/dashboard`.
- Each role dashboard is protected by `role:<role-name>` middleware.
- Owner is NOT granted implicit access to staff or courier dashboards.
- A user with a wrong role receives HTTP 403 rather than being silently
  redirected.

## Guard, provider and package boundaries

- Guard: default `web` session guard, session driver `database`.
- Provider: eloquent, model `App\Models\User`.
- No additional guards.
- No permissions or roles table.
- No third-party role or permission package (Spatie Permission, Bouncer,
  etc.) is installed.
- No API token authentication, social login, email verification or 2FA
  in this packet.

Rationale is captured in
`docs/decisions/ADR-007-role-based-session-authentication.md`.
