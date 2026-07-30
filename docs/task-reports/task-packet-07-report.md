# Task Packet 07 Report: Delivery Orders

- **Packet**: 07
- **Slice**: Delivery orders (drafts, scheduling, cancellation)
- **Starting commit**: `a0969e6 feat: add customer management with map selection`
- **Branch**: `main`
- **Date completed**: 2026-07-30

## Scope summary

Packet 07 introduces the delivery-order slice of the catering tracker.
The delivery order becomes the operational unit of the system,
represented by a finite state machine (five states, three implemented
transitions), a receipt numbering scheme, kitchen and customer
snapshots at scheduling time, and a configurable concurrency cap.

Owner and staff roles gain full access; couriers are excluded until
their surface is built in a later packet.

## Deliverables

### Governance

- AR-23..AR-28 appended to `docs/project/decision-log.md`.
- AR-27 recorded with a revision note ("single active delivery"
  revised to "configurable cap, default 1" pre-implementation).
- Packet 07 governance-audit note recorded: no invalid entries voided
  between AR-23 and AR-40; none existed.

### Configuration

- `config/delivery.php` created with five keys:
  `max_concurrent_active`, `receipt_prefix`, `receipt_random_length`,
  `receipt_random_alphabet`, `receipt_date_timezone`.
- `.env.example` appended with five matching `DELIVERY_*` keys and
  their defaults. `.env` untouched.

### Domain layer (`app/Domain/Delivery`)

- `DeliveryStatus` enum (5 backed values, 8 helper methods).
- `ReceiptNumberGenerator` service (`random_int` suffix, 10-retry
  uniqueness loop, throws `RuntimeException` on exhaustion).
- `DeliveryScheduler` service (transactional, `lockForUpdate` on
  delivery/kitchen/customer, snapshot + receipt + audit atomic).
- `DeliveryCanceller` service (reason 3..255, preserves receipt and
  snapshots, records `cancelled_by_user_id` and `cancelled_at`).
- Seven typed exceptions under
  `app/Domain/Delivery/Exceptions/`.

### Persistence

- Migration `2026_07_30_062930_create_deliveries_table.php` creating
  the `deliveries` table with FKs (`kitchen_id`, `customer_id`
  restrict-on-delete; `scheduled_by_user_id`, `cancelled_by_user_id`,
  `created_by_user_id` nullable/restrict).
- Ten snapshot columns (five kitchen, five customer). Coordinates use
  `decimal(10,7)`.
- Unique nullable `receipt_number varchar(20)`. Indexed `status` and
  `scheduled_at`. No soft-delete column.

### HTTP layer

- `App\Http\Controllers\DeliveryController` with 8 actions.
- Four FormRequests under `App\Http\Requests\Delivery`:
  `StoreDeliveryRequest`, `UpdateDeliveryRequest`,
  `ScheduleDeliveryRequest`, `CancelDeliveryRequest`.
- 8 routes under `auth`, `active`, `role:owner,staff` middleware.

### Presentation

- Blade views under `resources/views/deliveries/`: `index`, `create`,
  `show`, `edit`, plus partials `_form`, `_status_badge`, `_audit`,
  `_action_buttons`.
- Show and index branch on status to render live or snapshot data.
- Nav link added to `resources/views/layouts/app.blade.php` for owner
  and staff only.

### Tests

- Unit tests (4 files) under `tests/Unit/Domain/Delivery/`:
  `DeliveryStatusTest`, `ReceiptNumberGeneratorTest`,
  `DeliveryCancellerTest`, `DeliverySchedulerTest`.
- Feature tests (6 files) under `tests/Feature/Delivery/`:
  `DeliveryAuthorizationTest`, `DeliveryCrudTest`,
  `DeliveryStateMachineTest`, `DeliveryConcurrencyLimitTest`,
  `DeliveryValidationTest`, `DeliveryRouteTest`.
- `DeliveryFactory` with `scheduled`, `cancelledFromDraft`, and
  `cancelledFromScheduled` states.

### Documentation

- `docs/deliveries/delivery-management.md`
- `docs/deliveries/delivery-state-machine.md`
- `docs/deliveries/receipt-numbers.md`
- `docs/deliveries/snapshots-and-history.md`
- `docs/deliveries/concurrency-limit.md`
- `docs/requirements/delivery-requirements.md`
- `docs/decisions/ADR-010-delivery-state-machine.md`
- `docs/decisions/ADR-011-delivery-snapshots-and-receipt.md`
- `docs/decisions/ADR-012-delivery-concurrency-configurable.md`
- `docs/task-reports/task-packet-07-report.md` (this file)

Surgical updates to:

- `README.md` (features + workflows)
- `docs/architecture/project-structure.md` (deliveries surfaces)
- `docs/project/change-log.md` (Packet 07 entry)
- `docs/project/risk-register.md` (Packet 07 risks + mitigations)
- `docs/project/progress.md` (Packet 07 completion)

## Verification results

| Check                     | Result                                     |
| ------------------------- | ------------------------------------------ |
| `php artisan test`        | 235 tests, 653 assertions, 0 failures      |
| `composer validate --strict` | Valid                                   |
| `composer audit`          | No advisories                              |
| `npm audit`               | 0 vulnerabilities                          |
| `npm run build`           | Success                                    |
| `git diff --check`        | Clean                                      |
| `php artisan migrate`     | Applied cleanly to MySQL runtime           |

## Smoke test summary (127.0.0.1)

| Actor    | Action                          | Expected  | Actual |
| -------- | ------------------------------- | --------- | ------ |
| Guest    | `GET /deliveries`               | redirect  | 302 to `/login` |
| Owner    | Login                           | 302       | 302    |
| Owner    | `GET /deliveries`               | 200       | 200    |
| Owner    | Create draft                    | 302 to show | 302 to `/deliveries/1` |
| Owner    | Schedule delivery               | 302 to show | 302 to `/deliveries/1` |
| Owner    | Cancel scheduled delivery       | 302 to show | 302 to `/deliveries/1` |
| Staff    | `GET /deliveries`, `create`     | 200, 200  | 200, 200 |
| Courier  | `GET /deliveries`, `create`, `show/1` | 403 x3 | 403 x3 |

Post-schedule state: `receipt_number=DEL-20260730-TSZH`, snapshots
captured, `scheduled_by_user_id=1`. Post-cancel state: receipt and
snapshots preserved, `cancellation_reason` recorded,
`cancelled_by_user_id=1`.

Smoke test users and records were removed after verification.

## Notable implementation details

- `after:now` validation runs in the app timezone (Asia/Jakarta), so
  factory and test helpers use `now()` rather than `Carbon::now('UTC')`
  when preparing future timestamps. Storage remains UTC.
- Concurrency check excludes the current delivery id, allowing the
  scheduling transition itself to consume one of the cap slots without
  false rejection.
- Index ordering uses portable raw SQL that works on both MySQL and
  SQLite (test) targets.
- No delete route is registered; verified by
  `DeliveryRouteTest::test_delete_route_does_not_exist` returning
  `405 Method Not Allowed`.

## Risks and mitigations

| Risk                                    | Mitigation                            |
| --------------------------------------- | ------------------------------------- |
| Concurrent scheduling could race        | Row-level `lockForUpdate` on delivery; acceptable at Packet 07 volume; ADR-012 flags a tightening path if observed |
| Receipt collision                        | 4-char suffix over 30-char alphabet = 810,000 daily values; 10-retry loop absorbs the rare collision |
| Snapshot drift after source edit         | Snapshots captured atomically in scheduling transaction; unit test asserts immutability |
| Timezone confusion (UTC vs Jakarta)      | Storage UTC, display Jakarta; validation and factory helpers use app-tz `now()`; documented in delivery docs |
| Courier eventually needs access          | Middleware `role:owner,staff` is a single toggle point when courier surface arrives |

## Files touched

New files:

- `config/delivery.php`
- `app/Domain/Delivery/DeliveryStatus.php`
- `app/Domain/Delivery/ReceiptNumberGenerator.php`
- `app/Domain/Delivery/DeliveryScheduler.php`
- `app/Domain/Delivery/DeliveryCanceller.php`
- `app/Domain/Delivery/Exceptions/*` (7 files)
- `app/Http/Controllers/DeliveryController.php`
- `app/Http/Requests/Delivery/{Store,Update,Schedule,Cancel}DeliveryRequest.php`
- `app/Models/Delivery.php`
- `database/factories/DeliveryFactory.php`
- `database/migrations/2026_07_30_062930_create_deliveries_table.php`
- `resources/views/deliveries/*` (8 files)
- `tests/Unit/Domain/Delivery/*` (4 files)
- `tests/Feature/Delivery/*` (6 files)
- `docs/deliveries/*` (5 files)
- `docs/requirements/delivery-requirements.md`
- `docs/decisions/ADR-{010,011,012}*.md`
- `docs/task-reports/task-packet-07-report.md`

Surgical edits:

- `routes/web.php` (import + 8-route group)
- `resources/views/layouts/app.blade.php` (Deliveries nav link)
- `.env.example` (5 `DELIVERY_*` keys appended)
- `docs/project/decision-log.md` (AR-23..AR-28 + revision note + audit note)
- `README.md`
- `docs/architecture/project-structure.md`
- `docs/project/change-log.md`
- `docs/project/risk-register.md`
- `docs/project/progress.md`

## Out of scope reminder

Not delivered in Packet 07:

- Courier assignment, `scheduled to in_transit`, `in_transit to delivered`
- Pricing, distance, Haversine math
- Receipt tracking authorization or public page
- SMS, GPS telemetry, firmware
- API surface

These are the natural entry points for Packet 08.
