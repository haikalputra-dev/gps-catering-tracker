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

## Kitchen Slice (Packet 05)

The kitchen slice adds the first business entity with a coordinate
picker. Delivery, pricing, customer, tracking, device, and SMS work
remain deferred to later packets.

```text
app/Domain/Kitchen/KitchenCode.php               Code normalizer/validator
app/Models/Kitchen.php                           Model + active() scope + casts
app/Http/Controllers/KitchenController.php       Index/create/store/edit/update
app/Http/Requests/Kitchen/StoreKitchenRequest.php  Store validation + normalization
app/Http/Requests/Kitchen/UpdateKitchenRequest.php Update validation + normalization
database/migrations/2026_07_30_042022_create_kitchens_table.php  Schema
database/factories/KitchenFactory.php            Test/demo factory
config/map.php                                    Map picker configuration
resources/js/kitchen-map.js                       Leaflet integration (bundled)
resources/views/kitchens/index.blade.php          Listing (active first, paginated)
resources/views/kitchens/create.blade.php         Create form
resources/views/kitchens/edit.blade.php           Edit form
resources/views/kitchens/_form.blade.php          Shared form with map picker
```

See `docs/kitchens/kitchen-management.md`,
`docs/kitchens/map-coordinate-selection.md`, and
`docs/decisions/ADR-008-kitchen-lifecycle-and-coordinate-selection.md`.

## Customer Slice (Packet 06)

The customer slice adds the second first-class business entity. It
reuses the map configuration from the kitchen slice and follows the
same active/inactive lifecycle pattern. Delivery, pricing, tracking,
device, and SMS work remain deferred to later packets.

```text
app/Domain/Customer/CustomerPhone.php             Phone normalizer/validator
app/Models/Customer.php                           Model + active() scope + casts
app/Http/Controllers/CustomerController.php       Index/create/store/edit/update
app/Http/Requests/Customer/StoreCustomerRequest.php  Store validation + normalization
app/Http/Requests/Customer/UpdateCustomerRequest.php Update validation + normalization
database/migrations/2026_07_30_060000_create_customers_table.php  Schema
database/factories/CustomerFactory.php            Test/demo factory (Faker only)
resources/js/customer-map.js                      Leaflet integration (bundled)
resources/views/customers/index.blade.php         Listing (active first, phone masked)
resources/views/customers/create.blade.php        Create form
resources/views/customers/edit.blade.php          Edit form
resources/views/customers/_form.blade.php         Shared form with map picker
```

See `docs/customers/customer-management.md`,
`docs/customers/customer-phone-and-privacy.md`, and
`docs/decisions/ADR-009-customer-entity-and-lifecycle.md`.

## Delivery Slice (Packet 07)

The delivery slice introduces the operational unit of the tracker: a
five-state finite state machine, three implemented transitions
(`draft -> scheduled`, `draft -> cancelled`, `scheduled -> cancelled`),
receipt-number issuance, atomic kitchen and customer snapshots at
scheduling, and a configurable concurrency cap. Owner and staff only;
courier is deliberately excluded from Packet 07.

```text
app/Domain/Delivery/DeliveryStatus.php                Backed enum + helpers
app/Domain/Delivery/ReceiptNumberGenerator.php        DEL-YYYYMMDD-XXXX with retry
app/Domain/Delivery/DeliveryScheduler.php             Transactional scheduler
app/Domain/Delivery/DeliveryCanceller.php             Cancellation service
app/Domain/Delivery/Exceptions/                       Seven typed exceptions
app/Models/Delivery.php                               Model + scopes + relations
app/Http/Controllers/DeliveryController.php           8-action controller
app/Http/Requests/Delivery/                           Four FormRequests
config/delivery.php                                    Cap + receipt config
database/migrations/2026_07_30_062930_create_deliveries_table.php  Schema
database/factories/DeliveryFactory.php                Test/demo factory
resources/views/deliveries/                           Blade views + partials
```

See `docs/deliveries/delivery-management.md`,
`docs/deliveries/delivery-state-machine.md`,
`docs/deliveries/receipt-numbers.md`,
`docs/deliveries/snapshots-and-history.md`,
`docs/deliveries/concurrency-limit.md`,
`docs/decisions/ADR-010-delivery-state-machine.md`,
`docs/decisions/ADR-011-delivery-snapshots-and-receipt.md`, and
`docs/decisions/ADR-012-delivery-concurrency-configurable.md`.

## Delivery Pricing (Packet 08)

Packet 08 extends the delivery slice with two frozen values on the
scheduled delivery row: a straight-line Haversine `distance_km` and a
rupiah `fee_rupiah`. Both are computed once at `draft -> scheduled`,
preserved on cancellation, and never recomputed. Owner and staff only.
No new route, controller action, or FormRequest.

```text
app/Domain/Delivery/DistanceCalculator.php     Haversine (R=6371.0088)
app/Domain/Delivery/PricingCalculator.php      Config-driven rupiah fee
app/Domain/Delivery/DeliveryScheduler.php      Integrates both calculators
config/pricing.php                              Three-key pricing surface
database/migrations/2026_07_30_141932_add_distance_and_fee_to_deliveries_table.php
resources/views/deliveries/index.blade.php    Fee column
resources/views/deliveries/show.blade.php     Pricing card
```

See `docs/deliveries/pricing-and-distance.md` and
`docs/decisions/ADR-013-haversine-and-fee-formula.md`.

## Delivery Courier Lifecycle (Packet 09)

Packet 09 completes the delivery state machine. It binds a courier
to each delivery at scheduling time, adds the `scheduled →
in_transit → delivered` transitions as courier-initiated taps,
extends cancellation to cover `in_transit`, and gives the courier
role a functional dashboard. Fee remains hidden from courier-facing
surfaces.

```text
app/Domain/Delivery/DeliveryDispatcher.php     scheduled -> in_transit
app/Domain/Delivery/DeliveryCompleter.php      in_transit -> delivered
app/Domain/Delivery/DeliveryScheduler.php      + courier assertions
app/Domain/Delivery/DeliveryCanceller.php      + mid-route cancel matrix
app/Domain/Delivery/Exceptions/                8 additional typed exceptions
app/Http/Controllers/DeliveryController.php    + dispatch/markDelivered
app/Http/Controllers/DashboardController.php   + courier() action
routes/web.php                                 10 delivery routes total
config/delivery.php                            + max_concurrent_per_courier
database/migrations/2026_07_30_150000_add_courier_assignment_to_deliveries_table.php
resources/views/dashboard/courier.blade.php    Courier home surface
resources/views/deliveries/show.blade.php      Office-only Pricing card branch
```

See `docs/deliveries/courier-assignment.md`,
`docs/deliveries/dispatch-and-completion.md`,
`docs/deliveries/mid-route-cancellation.md`,
`docs/deliveries/courier-visibility-and-fee-privacy.md`,
`docs/decisions/ADR-014-courier-assignment-and-per-courier-limit.md`,
and `docs/decisions/ADR-015-dispatch-and-completion-via-manual-taps.md`.

## Placeholder Notice

The following directories still contain only a `.gitkeep` file:

```text
app/Domain/Tracking
app/Domain/Device
app/Application
app/Infrastructure
```

These are architectural placeholders, not completed modules. No models,
controllers, services, repositories, DTOs, enums, migrations, middleware, API
routes, or business logic have been created in these directories.
