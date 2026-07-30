# Project Structure

This project uses a layered architecture on top of the standard Laravel
directory conventions. Domain and application directories introduced in the
bootstrap packet are empty architectural placeholders and do not yet contain
implementation. The identity slice from Packet 04 is the first area with
production code and lives across `app/Domain/Identity`, `app/Http` and
`app/Console`.

## Layers

- `app/Domain` will contain domain-specific business concepts, grouped by bounded
  context (Kitchen, Delivery, Tracking, Device). These represent core business
  rules and entities.
- `app/Application` will orchestrate use cases, coordinating domain concepts to
  fulfill application workflows.
- `app/Infrastructure` will contain external integrations (persistence adapters,
  third-party services, device communication, etc.).

## Standard Laravel Directories

Laravel HTTP controllers, middleware, requests, and presentation logic remain
under the standard Laravel directories (`app/Http`, `resources/views`, `routes`,
etc.). Blade templates will provide the future frontend.

## Identity Slice (Packet 04)

The first slice with production code covers session-based authentication and
role management:

```text
app/Domain/Identity/UserRole.php            Role enum (owner/staff/courier)
app/Models/User.php                         Fillable + casts + role helpers
app/Http/Controllers/Auth/                  Login/logout controller
app/Http/Controllers/DashboardController.php Role dispatcher + role dashboards
app/Http/Controllers/Owner/UserController.php Owner user management
app/Http/Middleware/EnsureUserIsActive.php  active-account enforcement
app/Http/Middleware/RequireRole.php         role gating
app/Http/Requests/Auth/LoginRequest.php     login validation + throttling
app/Http/Requests/Owner/                    Owner user-management requests
app/Console/Commands/CreateOwnerCommand.php Initial owner provisioning
resources/views/auth/                       Login screen
resources/views/dashboard/                  Role dashboards
resources/views/owner/users/                Owner user-management UI
routes/web.php                              Route registrations
bootstrap/app.php                           Middleware alias registration
```

See `docs/authentication/role-access.md` and
`docs/decisions/ADR-007-role-based-session-authentication.md`.

## Placeholder Notice

The following directories still contain only a `.gitkeep` file:

```text
app/Domain/Kitchen
app/Domain/Delivery
app/Domain/Tracking
app/Domain/Device
app/Application
app/Infrastructure
```

These are architectural placeholders, not completed modules. No models,
controllers, services, repositories, DTOs, enums, migrations, middleware, API
routes, or business logic have been created in these directories.
