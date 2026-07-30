# Task Packet 05 - Kitchen Management Report

- Date: 2026-07-30
- Branch: `main`
- Starting commit: `1d7c43a feat: add role-based session authentication`
- Ending commit: (to be recorded after Step 24 commit)
- Runtime: Laravel 13.23.0, PHP 8.3.32
- Database: MySQL 8.0.46 (application), SQLite `:memory:` (tests)

## Scope delivered

Task Packet 05 adds a fully functional kitchen management surface for
owner and staff roles, backed by a Leaflet-based coordinate selector.
Delivery, pricing, customer, tracking, device, and SMS work remain
deferred to later packets, matching the packet brief.

The kitchen surface is intentionally small:

- Migration: one new table (`kitchens`).
- Routes: exactly 5 (`index`, `create`, `store`, `edit`, `update`);
  no delete route exists.
- Access: `auth` + `active` + `role:owner,staff`. Courier receives
  HTTP 403; guest is redirected to `/login`.
- Coordinate authority: server. Map picker is an input aid.
- Lifecycle: `is_active` boolean. Deactivation preserves data.

## Governance actions

- AR-16..AR-20 in `docs/project/decision-log.md` were retained but
  marked Void with an audit note. They were introduced by Packet 04
  without PM approval and are preserved for auditability only.
- AR-21 added and approved: kitchens use an active/inactive lifecycle
  with no hard deletion.
- ADR-008 created to capture the lifecycle and coordinate-selection
  choices in a single Architecture Decision Record.

## Files added

Application code:

- `database/migrations/2026_07_30_042022_create_kitchens_table.php`
- `app/Domain/Kitchen/KitchenCode.php`
- `app/Models/Kitchen.php`
- `app/Http/Requests/Kitchen/StoreKitchenRequest.php`
- `app/Http/Requests/Kitchen/UpdateKitchenRequest.php`
- `app/Http/Controllers/KitchenController.php`
- `config/map.php`
- `database/factories/KitchenFactory.php`

Frontend assets:

- `resources/js/kitchen-map.js`
- `resources/views/kitchens/index.blade.php`
- `resources/views/kitchens/create.blade.php`
- `resources/views/kitchens/edit.blade.php`
- `resources/views/kitchens/_form.blade.php`

Tests:

- `tests/Unit/Domain/Kitchen/KitchenCodeTest.php`
- `tests/Feature/Kitchen/KitchenAuthorizationTest.php`
- `tests/Feature/Kitchen/KitchenManagementTest.php`
- `tests/Feature/Kitchen/KitchenLifecycleTest.php`
- `tests/Feature/Kitchen/KitchenValidationTest.php`
- `tests/Feature/Kitchen/KitchenRouteTest.php`

Documentation:

- `docs/kitchens/kitchen-management.md`
- `docs/kitchens/map-coordinate-selection.md`
- `docs/requirements/kitchen-requirements.md`
- `docs/decisions/ADR-008-kitchen-lifecycle-and-coordinate-selection.md`
- `docs/task-reports/task-packet-05-report.md` (this file)

## Files modified

- `routes/web.php` - added the kitchen route group under
  `auth`, `active`, `role:owner,staff`.
- `resources/views/layouts/app.blade.php` - added a "Kitchens" nav
  link visible only when the current user is owner or staff.
- `resources/js/app.js` - imports `./kitchen-map.js`.
- `resources/css/app.css` - added `#kitchen-map`,
  `#kitchen-coordinate-display`, and `.kitchen-map-instruction`
  styles.
- `.env.example` - added `MAP_*` environment variables.
- `package.json` / `package-lock.json` - added `leaflet@1.9.4` with
  `--save-exact`.
- `docs/project/decision-log.md` - marked AR-16..AR-20 as Void with an
  audit note; added AR-21.

Companion project-management doc updates (progress, change log,
requirements traceability, risk register, project structure) are being
performed as surgical edits alongside this report.

## Verification

### Test suite

```
php artisan test
Tests:    98 passed  (273 assertions)
Duration: ~2.1s
```

Breakdown of Packet 05 additions (57 tests):

- `tests/Unit/Domain/Kitchen/KitchenCodeTest.php`: 7 tests.
- `tests/Feature/Kitchen/KitchenAuthorizationTest.php`: 12 tests.
- `tests/Feature/Kitchen/KitchenManagementTest.php`: 5 tests.
- `tests/Feature/Kitchen/KitchenLifecycleTest.php`: 7 tests.
- `tests/Feature/Kitchen/KitchenValidationTest.php`: 17 tests.
- `tests/Feature/Kitchen/KitchenRouteTest.php`: 4 tests.

Combined with Packet 04's 45 tests / 132 assertions, everything still
passes.

### Quality gates

- `npm run build` succeeds. Leaflet marker assets emitted at
  `public/build/assets/marker-icon-*.png`,
  `public/build/assets/marker-icon-2x-*.png`,
  `public/build/assets/marker-shadow-*.png`.
- `composer validate --strict` reports `./composer.json is valid`.
- `composer audit` reports `No security vulnerability advisories found.`
- `npm audit` reports `found 0 vulnerabilities`.
- `git diff --check` reports clean.

### Migration status (MySQL 8.0.46)

```
0001_01_01_000000_create_users_table                    [1] Ran
0001_01_01_000001_create_cache_table                    [1] Ran
0001_01_01_000002_create_jobs_table                     [1] Ran
2026_07_30_042022_create_kitchens_table                 [3] Ran
2026_07_30_103737_add_role_and_status_to_users_table    [2] Ran
```

Only `kitchens` was added. No structural change to `users`, `cache`,
or `jobs`. No `migrate:fresh` was executed.

### Dependencies added

- `leaflet@1.9.4` pinned via `npm install --save-exact`. `npm ls leaflet`
  reports `leaflet@1.9.4`. No transitive vulnerabilities reported.

No PHP composer packages were added or removed in this packet.

## Design notes

### Coordinate authority

The map picker is a client aid. Both the `store` and `update` request
classes always require and validate `latitude` and `longitude`. Casts
on the model round to 7 decimal places. Any future non-web client
(mobile, importer) will pass the same server-side gate.

### Code normalization

`KitchenCode::normalize()` trims whitespace and uppercases. Both form
requests apply this in `prepareForValidation()` before rules run. This
means the pattern rule `/^[A-Z0-9-]+$/` is checked against the exact
value that will be persisted, and the uniqueness rule uses the
canonical stored form.

### Lifecycle without delete

`is_active` is a boolean column with an index and a default of true.
Deactivating a kitchen updates only `is_active`. No `deleted_at`,
observer, cascade, or restore path exists. The absence of a delete
route is enforced by:

- `routes/web.php` not registering DELETE.
- `KitchenLifecycleTest::test_no_delete_route_returns_405`.
- `KitchenRouteTest::test_no_delete_route_exists`.

### Nav visibility

The "Kitchens" nav link uses the existing `isOwner()` and `isStaff()`
helpers on the `User` model. Couriers see the same shell without the
link. The route is still protected by middleware even if a courier
crafts the URL manually.

## Deferred items (out of scope)

- Delivery scheduling and routing.
- Pricing configuration.
- Customer records.
- Live tracking.
- SMS gateway integration.
- Device firmware endpoints.
- Bulk import/export.
- Public API for kitchens.
- Image uploads.
- Kitchen service radius or polygon.

These are called out in the requirements document so future packets do
not reintroduce them silently.

## Follow-up recommendations

- Deferred delivery scheduling should query `Kitchen::active()` when it
  arrives.
- Any future coordinate consumer (e.g., distance matrix) should read
  coordinates from the model rather than the request payload to keep
  the server as the source of truth.
- If OSM tile volume becomes a concern, switch `MAP_TILE_URL` and
  `MAP_TILE_ATTRIBUTION` per the map-coordinate-selection doc; no code
  change is required.
- Consider adding an offline seed of a handful of demonstration
  kitchens once the delivery packet needs fixtures; do NOT extend
  `DatabaseSeeder` in this packet because the stock `test@example.com`
  user factory must remain the only seed in production.

## Sign-off

- Governance corrected (AR-16..AR-20 marked Void, AR-21 added).
- All 98 tests pass.
- All quality gates green.
- Single migration applied cleanly against MySQL.
- No push to remote; local `main` commit only.
