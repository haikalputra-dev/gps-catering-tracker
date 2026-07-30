# Task Packet 10 Report: Customer Delivery Tracking

- **Packet**: 10
- **Slice**: Public receipt-based tracking form and session-scoped
  status page for customers. No customer accounts, SMS, OTP, signed
  URLs, WebSockets, polling, live map, or telemetry.
- **Starting commit**: `d81a2a9 feat: add courier assignment, dispatch,
  and completion to delivery lifecycle`
- **Branch**: `main`
- **Date completed**: 2026-07-30

## Scope summary

Packet 10 adds the customer-facing tracking surface at `/track`. A
customer enters the receipt number and the last four digits of the
phone number on their order; on success, a session-scoped status page
renders the current state, the frozen kitchen and customer snapshots,
distance and fee, a step timeline, and (only while the delivery is
`in_transit`) the assigned courier's name and phone.

No changes were made to the delivery state machine, to any migration,
to the `/home/ubuntu/GPS-server` project, or to `.env`. All existing
tests continue to pass and the delivery route surface remains at
exactly ten routes.

## Deliverables

### Governance

- `AR-42..AR-46` appended to `docs/project/decision-log.md`; the
  decision-log header updated to `AR-01 through AR-46`.
- `AR-42` marked "Approved (revised)" to record the pre-implementation
  refinements: `hash_equals` for phone-last-4 comparison, generic
  error copy identical across all failure modes, session-id
  regeneration on success, and `throttle:10,15` on the POST endpoint.
- Packet 10 governance-audit note recorded: no invalid entries voided
  between `AR-42` and `AR-55`; the audit range was empty above `AR-46`.

### Domain

- `App\Domain\Tracking\TrackingAuthenticator`: stateless service that
  resolves a `Delivery` from a raw receipt number and phone-last-4.
  Normalizes receipts (trim, uppercase, strip non-alphanumerics; if
  15-char stripped form, re-insert hyphens to `DEL-YYYYMMDD-XXXX`).
  Validates against `/^DEL-\d{8}-[A-Z0-9]{4}$/`. Excludes drafts.
  Extracts the last four digits of the snapshot phone and compares
  with `hash_equals`. Every failure mode returns `null`; no exceptions
  are thrown for user input.

### HTTP

- `App\Http\Requests\Tracking\TrackingAuthenticateRequest`: two-field
  form request. `failedValidation()` collapses every rule failure -
  across either field - into a single form-level error keyed `form`,
  with the same generic copy the controller uses on a domain failure.
- `App\Http\Controllers\TrackingController` with four actions:
  - `form` renders `tracking.form` or redirects to `tracking.status`
    if the session already carries a valid delivery id. Stale, draft,
    or non-numeric values are cleared without throwing.
  - `authenticate` runs the domain service, on success regenerates
    the session id (fixation defense) and stores `tracking.delivery_id`,
    on failure redirects back with the generic error and echoes only
    the receipt as old input.
  - `status` renders `tracking.status` for the session delivery, or
    redirects to the form with a flashed `info` when the session key
    no longer resolves to a trackable row.
  - `signOut` forgets the session key, regenerates the CSRF token,
    and returns to the form.

### Routes

Four routes added to `routes/web.php`:

- `GET /track` -> `tracking.form`
- `POST /track` with `throttle:10,15` -> `tracking.authenticate`
- `GET /track/status` -> `tracking.status`
- `POST /track/sign-out` -> `tracking.signOut`

All four sit outside the `auth` middleware group. The `throttle`
alias resolves to Laravel's `Illuminate\Routing\Middleware\ThrottleRequests`
via the framework's default middleware configuration; no changes to
`bootstrap/app.php` were required.

### Views

- `resources/views/layouts/public.blade.php`: new minimal layout used
  only for tracking pages. It does not render the authenticated app
  header, so no admin links can leak onto a public page. Emits
  `<meta name="robots" content="noindex, nofollow">`.
- `resources/views/tracking/form.blade.php`: receipt + phone-last-4
  entry form. `inputmode="numeric"` and `pattern="[0-9]{4}"` for
  mobile keyboards; the server remains the sole authority.
- `resources/views/tracking/status.blade.php`: renders the delivery
  status badge, receipt, kitchen and customer snapshots, distance and
  fee (formatted in km and Rupiah), a step timeline (Scheduled /
  Dispatched / Delivered, with a Cancelled row when applicable), and
  courier name and phone gated on `status === in_transit`. Timestamps
  render in `Asia/Jakarta`.

### Tests

Six new files, all passing:

| File | Tests | Focus |
| --- | --- | --- |
| `tests/Unit/Domain/Tracking/TrackingAuthenticatorTest.php` | 11 | Receipt normalization (case, separators, whitespace), format rejection, malformed phone rejection, draft exclusion, all four trackable statuses, missing snapshot phone |
| `tests/Feature/Tracking/TrackingFormTest.php` | 7 | Public form rendering, no authenticated header, redirect when session valid, stale session cleanup (missing, draft, non-numeric), noindex meta |
| `tests/Feature/Tracking/TrackingAuthenticateTest.php` | 11 | Happy path, session regeneration, uniform generic-error copy across every failure mode, receipt echo, phone not echoed, draft rejection, whitespace / case tolerance |
| `tests/Feature/Tracking/TrackingStatusTest.php` | 13 | Snapshot rendering, distance/fee formatting, status-badge coverage, courier-only-in-transit, cancellation row, no leaflet / websocket / setInterval / pusher, no admin fields, guest access |
| `tests/Feature/Tracking/TrackingSignOutTest.php` | 5 | Session clear, idempotency, CSRF rotation, no side effect on the delivery, sign-out button rendered on status page |
| `tests/Feature/Tracking/TrackingThrottleTest.php` | 4 | 10-attempt window, blocked 11th, limiter reset restores access, GET immunity |

Also updated: `tests/Feature/Delivery/DeliveryRouteTest.php`. The
Packet 07 guard test `test_public_receipt_lookup_route_does_not_exist`
became `test_only_the_tracking_route_exposes_receipt_lookup`. `/track`
now legitimately serves 200; variants such as `/tracking`, `/lookup`,
`/api/track`, and `/api/tracking` still 404.

### Documentation

- `docs/tracking/customer-tracking.md`: topic doc describing the
  surface, factors, service contract, form request, controller flow,
  session semantics, view responsibilities, throttling behavior,
  non-goals, and the test-coverage matrix.
- `docs/project/change-log.md`: 2026-07-30 - Packet 10 entry added at
  the top with Added / Behavior / Tests / Decisions sections.
- `docs/project/progress.md`: "Customer delivery tracking (public
  receipt lookup, session-scoped status)" line added between the
  courier-lifecycle line and the "Domain implementation" bucket.

## Verification

### Test suite

```
php artisan test
Tests: 374, Assertions: 1107, Duration: 4.2s
Result: passed
```

Baseline before Packet 10 was 323 tests / 877 assertions. Packet 10
added 51 tests (11 unit + 40 feature) and 230 assertions, and updated
one existing test in place (net +1 assertion on that test).

### Static audits

- `composer audit`: `No security vulnerability advisories found.`
- `npm audit --production`: `found 0 vulnerabilities`.

### Frontend build

- `npm run build`: succeeds. No new JS or CSS was introduced for
  tracking; the two Blade views use inline styles inside the public
  layout, so the existing Vite manifest is unchanged in structure.

### Route surface

`php artisan route:list --path=track` reports exactly four routes:
`tracking.form`, `tracking.authenticate`, `tracking.status`,
`tracking.signOut`. The delivery route surface is unchanged at ten
routes.

## Decisions applied

- **AR-42 (revised)**: receipt + phone-last-4 authentication,
  `hash_equals` comparison, generic error copy, session-scoped access,
  `throttle:10,15` on the POST endpoint.
- **AR-43**: session-scoped tracking with session-id regeneration on
  authentication and explicit sign-out.
- **AR-44**: draft deliveries are never trackable.
- **AR-45**: courier name and phone visible on the customer status
  page only while the delivery is `in_transit`; hidden in every other
  status.
- **AR-46**: no live map, no polling, no WebSockets, no telemetry, no
  API surface introduced for tracking.

## Out of scope (unchanged)

- Customer user accounts, SMS delivery, OTP, magic links, signed URLs.
- Real-time position updates or a live map on the customer page.
- Analytics, telemetry, or per-customer event logs.
- API surfaces (`/api/track`, `/api/tracking`, etc.).
- Any change to the delivery state machine, delivery migrations, or
  the `/home/ubuntu/GPS-server` project.
