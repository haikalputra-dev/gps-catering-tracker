# Task Packet 06 Report — Customer Management with Map Selection

## Summary

Packet 06 introduces the customer entity: the second first-class
business record in the system after kitchens. Owners and staff can
create, list, and edit customers; couriers cannot. Customers use an
active/inactive lifecycle with no delete route, mirroring the kitchen
pattern set in Packet 05. Coordinates are chosen with the same
Leaflet 1.9.4 stack the kitchen form uses, driven by the existing
`config/map.php` values.

Governance record `AR-22` was added to `docs/project/decision-log.md`
with owner approval before implementation began. `ADR-009` records
the decision in long form.

## Scope delivered

- Migration `2026_07_30_060000_create_customers_table.php`.
- `App\Models\Customer` with `active` scope, boolean and
  decimal(10,7) casts, and `HasFactory` wiring.
- `App\Domain\Customer\CustomerPhone` normalizer/validator with
  constants `MIN_DIGITS=9` and `MAX_DIGITS=15`.
- Form requests `StoreCustomerRequest` and `UpdateCustomerRequest`
  with `prepareForValidation` normalization and unique-with-ignore
  semantics.
- `App\Http\Controllers\CustomerController` with five actions:
  index, create, store, edit, update. Pagination at 15,
  active-first ordering.
- Route group `role:owner,staff` under existing `auth`, `active`
  middleware. Exactly five named routes; no `customers.destroy`.
- `resources/js/customer-map.js`, wired into `resources/js/app.js`,
  with distinct DOM identifiers from the kitchen module.
- CSS additions in `resources/css/app.css` scoped to
  `#customer-map`, `#customer-coordinate-display`,
  `.customer-map-instruction`, `.customer-phone-masked`, and
  customer textareas.
- Blade views `customers/index`, `customers/create`,
  `customers/edit`, `customers/_form`.
- Navigation link in `resources/views/layouts/app.blade.php` shown
  only to owner/staff.
- `Database\Factories\CustomerFactory` with `inactive` state and
  Faker-only data. `DatabaseSeeder` unchanged.
- Tests:
  - `tests/Unit/Domain/Customer/CustomerPhoneTest.php`
  - `tests/Feature/Customer/CustomerAuthorizationTest.php`
  - `tests/Feature/Customer/CustomerManagementTest.php`
  - `tests/Feature/Customer/CustomerValidationTest.php`
  - `tests/Feature/Customer/CustomerRouteTest.php`

## Explicitly out of scope

- Delivery scheduling, kitchen-customer linkage, pricing, tracking,
  device provisioning, SMS, firmware.
- Customer login, customer portal, customer API.
- Customer soft-delete, bulk import, data export.

## Verification

- `php artisan test`: 153 passed / 397 assertions.
- New Customer tests: 55 passed / 124 assertions.
- `vendor/bin/pint` applied to new/modified Customer files only.
- `npm run build`: succeeded; Leaflet marker images emitted.
- `composer audit`: no advisories.
- `npm audit`: 0 vulnerabilities.
- `php artisan migrate --database=mysql`: applied
  `2026_07_30_060000_create_customers_table` in batch 4.
- `php artisan route:list --name=customers`: exactly 5 routes,
  no destroy.
- Live curl smoke: not run in this environment; browser flow
  verified indirectly by controller/route/view tests plus a
  successful Vite build.

## Files touched

New:

- `database/migrations/2026_07_30_060000_create_customers_table.php`
- `app/Models/Customer.php`
- `app/Domain/Customer/CustomerPhone.php`
- `app/Http/Requests/Customer/StoreCustomerRequest.php`
- `app/Http/Requests/Customer/UpdateCustomerRequest.php`
- `app/Http/Controllers/CustomerController.php`
- `resources/js/customer-map.js`
- `resources/views/customers/_form.blade.php`
- `resources/views/customers/index.blade.php`
- `resources/views/customers/create.blade.php`
- `resources/views/customers/edit.blade.php`
- `database/factories/CustomerFactory.php`
- `tests/Unit/Domain/Customer/CustomerPhoneTest.php`
- `tests/Feature/Customer/CustomerAuthorizationTest.php`
- `tests/Feature/Customer/CustomerManagementTest.php`
- `tests/Feature/Customer/CustomerValidationTest.php`
- `tests/Feature/Customer/CustomerRouteTest.php`
- `docs/customers/customer-management.md`
- `docs/customers/customer-phone-and-privacy.md`
- `docs/requirements/customer-requirements.md`
- `docs/decisions/ADR-009-customer-entity-and-lifecycle.md`
- `docs/task-reports/task-packet-06-report.md`

Modified (surgical):

- `routes/web.php` — added customer route group.
- `resources/js/app.js` — imported `customer-map.js`.
- `resources/css/app.css` — added customer-scoped styles.
- `resources/views/layouts/app.blade.php` — nav link for
  owner/staff.
- `docs/project/decision-log.md` — AR-22 approved + audit note.
- Related surgical doc updates in README, project-structure,
  change-log, risk-register, progress.

## Follow-ups for later packets

- Wire customers into future delivery, tracking, and pricing
  entities. Foreign keys are intentionally absent in Packet 06 so
  those packets can specify the exact referential behavior they
  want.
- Consider a shared JavaScript helper if a third map surface
  appears; two near-duplicate map modules are acceptable, three
  is a smell.
