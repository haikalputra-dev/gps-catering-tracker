# Customer Tracking Requirements

Source-of-truth requirements for the customer-facing tracking surface.
Scope is Packet 10: a public form at `/track`, session-scoped access
to a static status page, and a small guard on the delivery route
surface. No customer accounts, SMS, OTP, signed URLs, real-time
updates, live map, telemetry ingestion, or API endpoint.

## R-TRK-01: Public entry point

A single form at `GET /track` (route name `tracking.form`) presents
two inputs - receipt number and phone-last-4 - and no other links,
buttons, or navigation to authenticated surfaces. The form is served
outside the `auth` middleware group and never requires a Laravel
session user.

## R-TRK-02: Authentication factors

Successful authentication requires both:

- A receipt number in the canonical shape `DEL-YYYYMMDD-XXXX`.
- The last four digits of the phone number stored in the delivery
  snapshot (`customer_phone`, frozen at scheduling per AR-26).

No other factor - no email, no OTP, no signed link, no account
password - is accepted or offered.

## R-TRK-03: Receipt normalization

Before format validation, the submitted receipt is trimmed,
non-alphanumerics are stripped, and the result is upper-cased. If
the stripped form is exactly 15 characters, hyphens are re-inserted
to reconstruct `DEL-YYYYMMDD-XXXX`. Otherwise the upper-cased
trimmed input is used as-is. The final string is validated against
`/^DEL-\d{8}-[A-Z0-9]{4}$/`.

## R-TRK-04: Phone-last-4 normalization

The submitted phone-last-4 is trimmed. The canonical shape is
exactly four ASCII decimal digits (`\d{4}`). Any other value fails
authentication.

## R-TRK-05: Uniform-failure contract

Every failure mode - malformed receipt, malformed phone-last-4,
unknown receipt, receipt pointing to a draft, correct receipt with
wrong phone-last-4, missing snapshot phone - yields the same generic
error copy at the same location:

> Invalid receipt or phone digits. Please check and try again.

The domain service returns `null` for every failure. The form
request collapses all rule failures across both fields into one
form-level error keyed `form`. The controller uses the same copy on
domain failure. Callers cannot distinguish failure modes from
observed behavior.

## R-TRK-06: Constant-time phone comparison

The comparison between the four-digit slice of the snapshot phone
and the submitted digits uses `hash_equals`. Length mismatch, empty
snapshot phone, or absent digits fall through to a null return
without exception.

## R-TRK-07: Draft exclusion

Deliveries in `status = draft` are never trackable, regardless of
whether a receipt exists on the row. The domain query restricts to
`scheduled`, `in_transit`, `delivered`, `cancelled`. This is
enforced both at authentication time and in the status render path
(a stale session key pointing at a draft silently clears).

## R-TRK-08: Session semantics

- Key: `tracking.delivery_id` (integer or numeric string).
- Written by `authenticate` on success.
- Session ID regenerated on write (`session()->regenerate()`).
- Read by `form` and `status`; silently cleared when it no longer
  resolves to a trackable delivery.
- Cleared explicitly by `signOut`, which also rotates the CSRF token
  (`session()->regenerateToken()`).
- No explicit lifetime override; persists until browser close.

## R-TRK-09: Status page content

The status page (`GET /track/status`, route name `tracking.status`)
renders:

- Receipt number and current status badge.
- Kitchen snapshot: name and address.
- Customer snapshot: name and address (for the customer's own
  confirmation).
- Scheduled arrival time in `Asia/Jakarta`.
- Distance in kilometres and fee in Rupiah (Indonesian
  dot-thousands, e.g. `Rp 12.345`).
- Step timeline with reached-state timestamps: Scheduled, Dispatched,
  Delivered. A Cancelled row appears when applicable, with the
  cancellation timestamp and reason.
- Assigned courier name and phone **only** while status is
  `in_transit`. Absent from the DOM in every other status.

Fields not rendered: `cancelled_by_user_id`, `created_by_user_id`,
`scheduled_by_user_id`, other deliveries for the same customer, or
any internal audit metadata.

## R-TRK-10: Static page contract

The status page renders once per request. It must not include:

- Leaflet or any client-side map library.
- Any `<script>` referencing `pusher`, `websocket`, or `socket.io`.
- `setInterval`, `setTimeout` polling to a status endpoint.
- `<meta http-equiv="refresh">` or equivalent.
- Any live-update surface.

Customers reload manually. Real-time behavior is deferred until
telemetry lands (Packet 11+).

## R-TRK-11: Public layout isolation

Tracking views extend `resources/views/layouts/public.blade.php`. The
public layout does not render the authenticated app header. No
admin link, no "Deliveries" link, no owner/staff/courier
navigation, and no sign-in-as-staff affordance appears on any
tracking page. The layout emits
`<meta name="robots" content="noindex, nofollow">`.

## R-TRK-12: Sign-out link

The status page renders a form-post link (labelled "Look up another
delivery") that invokes `POST /track/sign-out` (route name
`tracking.signOut`). The action clears `tracking.delivery_id`,
rotates the CSRF token, and redirects to `tracking.form`. There is
no separate "log out" button.

## R-TRK-13: Throttling

`POST /track` uses the framework built-in `throttle:10,15` - ten
attempts per fifteen minutes per route + IP signature. Both `GET`
routes and `POST /track/sign-out` are unthrottled. Rate limit
exhaustion returns Laravel's default `429` response. The limiter is
route-scoped; there is no application-wide named limiter for
tracking.

## R-TRK-14: Input echo policy

On failure, the receipt is echoed back to the form as old input so
the customer does not retype it. The phone-last-4 is never echoed
back. The controller uses `withInput(request()->only('receipt_number'))`.

## R-TRK-15: Guest access is expected

Tracking routes are for guests. Authenticated Laravel sessions do
not affect tracking session state and vice versa - the two session
keys occupy separate namespaces (`tracking.delivery_id` vs the
default auth cookie). A signed-in owner can hit `/track` and use the
form; a customer with a tracking session can never see admin
surfaces.

## R-TRK-16: No API surface

No `/api/track`, `/api/tracking`, or JSON endpoint is registered.
Attempts to fetch such a path return `404`. The tracking surface is
Blade-rendered HTML only.

## R-TRK-17: No admin surface changes

Packet 10 introduces no columns, no migrations, no changes to the
delivery state machine, and no changes to any existing admin route.
The `deliveries.*` route surface remains at exactly ten routes from
Packet 09.

## Traceability

| Requirement | Approved row | Implementing artifact |
| --- | --- | --- |
| R-TRK-01 | AR-42 (revised) | `routes/web.php` tracking group |
| R-TRK-02 | AR-07, AR-42 (revised) | `TrackingAuthenticator`, `TrackingAuthenticateRequest` |
| R-TRK-03, 04 | AR-42 (revised) | `TrackingAuthenticator::normalize*()` |
| R-TRK-05 | AR-42 (revised) | Form-request `failedValidation()`, controller error copy |
| R-TRK-06 | AR-42 (revised) | `TrackingAuthenticator::attempt()` |
| R-TRK-07 | AR-44 | `TrackingAuthenticator` query, `TrackingController::form/status` guards |
| R-TRK-08 | AR-43 | Session key writes in `TrackingController` |
| R-TRK-09 | AR-45 | `tracking/status.blade.php` |
| R-TRK-10 | AR-45, AR-46 | `tracking/status.blade.php` static-only assertions |
| R-TRK-11 | AR-42 (revised) | `layouts/public.blade.php` |
| R-TRK-12 | AR-43 | `TrackingController::signOut`, `tracking/status.blade.php` |
| R-TRK-13 | AR-42 (revised) | `throttle:10,15` on `POST /track` |
| R-TRK-14 | AR-42 (revised) | `TrackingController::authenticate` old-input filter |
| R-TRK-15 | AR-42 (revised) | Tracking routes outside `auth` group |
| R-TRK-16 | AR-46 | Absence of any `/api/track*` route |
| R-TRK-17 | AR-46 | `routes/web.php` delivery routes unchanged |

## Out of scope (unchanged)

- Customer user accounts, sign-up, email verification.
- SMS delivery, OTP, magic links, signed URLs.
- Live map on the customer page (Leaflet, Google Maps, or otherwise).
- Real-time position updates (polling, WebSocket, SSE, Pusher).
- Analytics, per-customer event logs, funnel tracking.
- API surfaces (`/api/track`, `/api/tracking`, JSON responses).
- Any change to `deliveries` columns or the delivery state machine.
