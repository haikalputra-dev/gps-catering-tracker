# Customer delivery tracking

Packet 10 introduces a public, session-scoped tracking surface so
customers can look up the status of a delivery without an account,
SMS, OTP, signed link, or live-map integration. This document
describes the surface as it exists in the code.

## Routes

All four routes are declared in `routes/web.php` alongside the auth
routes.

| Method | Path | Name | Middleware |
| --- | --- | --- | --- |
| GET | `/track` | `tracking.form` | (web) |
| POST | `/track` | `tracking.authenticate` | `throttle:10,15` |
| GET | `/track/status` | `tracking.status` | (web) |
| POST | `/track/sign-out` | `tracking.signOut` | (web) |

The routes are outside the `auth` middleware group; they never
require a Laravel-authenticated user.

## Authentication factors

- **Receipt number** issued at scheduling time (Packet 07). Canonical
  shape: `DEL-YYYYMMDD-XXXX`, where `XXXX` is 4 alphanumerics.
- **Last 4 digits of the customer's phone**, taken from the delivery
  snapshot (frozen at scheduling, AR-26).

Both values are user-visible on the customer's paper or email receipt.
Neither is a secret in the cryptographic sense - together they are a
low-friction identity check appropriate for the tracking scope agreed
in AR-42 (revised).

## Domain service

`App\Domain\Tracking\TrackingAuthenticator::attempt(receipt, phone4)`
returns the matching `Delivery` or `null`.

Steps:

1. Normalize the receipt: trim, strip all non-alphanumerics, uppercase.
   If the resulting form is exactly 15 characters, re-insert the two
   hyphens to produce `DEL-YYYYMMDD-XXXX`. Otherwise uppercase the
   trimmed input as-is. Validate the final string against
   `/^DEL-\d{8}-[A-Z0-9]{4}$/`.
2. Normalize the phone-last-4: trim, require exactly `\d{4}`.
3. Query for a delivery with the receipt where `status` is one of
   `scheduled`, `in_transit`, `delivered`, `cancelled`. Drafts are
   excluded even if their receipt is somehow known.
4. Extract the last 4 digits of the snapshot phone
   (`substr(preg_replace('/\D/', '', $customer_phone), -4)`).
5. Compare with `hash_equals` for constant-time semantics.

Any failure at any step returns `null`. Callers cannot distinguish
"bad format", "unknown receipt", "wrong phone", or "draft delivery"
from the return value.

## Form request

`App\Http\Requests\Tracking\TrackingAuthenticateRequest` enforces:

- `receipt_number`: `required`, `string`, `max:30`.
- `phone_last_four`: `required`, `string`, `size:4`, `regex:/^\d{4}$/`.

`prepareForValidation` trims and upper-cases the receipt and trims the
phone. `failedValidation` overrides the default behavior so every
rule failure (across either field) is collapsed into a single form-
level error, keyed `form`, with the same generic copy that
`TrackingController::authenticate` uses on a domain failure:

> Invalid receipt or phone digits. Please check and try again.

The receipt is echoed back as old input so users don't have to retype
it. The phone-last-4 is never echoed back.

## Controller

`App\Http\Controllers\TrackingController`:

- `form(Request)`: renders `tracking.form`. If the session already
  holds a valid tracking id, redirects to `tracking.status`; if the
  id is stale, non-numeric, or points at a draft, it is silently
  cleared.
- `authenticate(TrackingAuthenticateRequest, TrackingAuthenticator)`:
  runs `attempt()`, sets `tracking.delivery_id` in the session and
  regenerates the session id on success, or redirects back with the
  generic error on failure.
- `status(Request)`: renders `tracking.status` for the session
  delivery. Missing / stale / draft session values redirect back to
  the form with a flashed `info` message.
- `signOut(Request)`: forgets the session key, regenerates the CSRF
  token, and redirects to the form.

## Session

- Key: `tracking.delivery_id` (integer or numeric string).
- Regenerated on successful authentication (`session()->regenerate()`)
  to prevent fixation.
- Cleared on sign-out and CSRF token rotated.
- Cleared silently by `form()` and `status()` when the value no
  longer resolves to a trackable delivery.

## Views

- `resources/views/layouts/public.blade.php`: a minimal public layout
  containing only the tracking chrome. It does not render the
  authenticated app header, so no admin links can leak onto a public
  page. Emits `<meta name="robots" content="noindex, nofollow">`.
- `resources/views/tracking/form.blade.php`: receipt + phone-last-4
  entry form. Uses `inputmode="numeric"` and `pattern="[0-9]{4}"` for
  mobile keyboards without validating client-side (server is the
  authority).
- `resources/views/tracking/status.blade.php`: renders the delivery
  status badge, receipt, kitchen snapshot, customer snapshot,
  distance and fee, and a step timeline (Scheduled / Dispatched /
  Delivered, with a Cancelled row when applicable). Courier name and
  phone appear only while the delivery is `in_transit`.

## Throttling

`POST /track` uses `throttle:10,15` (10 attempts per 15 minutes per
route + IP signature, per Laravel's default rate-limiter key). The
GET routes are not throttled so an honest customer who mis-typed
cannot lock themselves out of the form itself.

The throttle counts every POST, whether successful or not. This is
intentional: for a public route with no user identity, request volume
is the only signal available.

## Non-goals

The following are explicitly out of scope for Packet 10:

- Customer user accounts, SMS delivery, OTP, magic links, signed URLs
- Real-time position updates (WebSockets, polling, SSE, Pusher)
- A live map on the customer page (Leaflet or otherwise)
- Analytics, telemetry, or per-customer event logs
- API surfaces (`/api/track`, `/api/tracking`, etc.)

## Test coverage

| File | Focus |
| --- | --- |
| `tests/Unit/Domain/Tracking/TrackingAuthenticatorTest.php` | Normalization, format validation, uniform-failure contract, coverage across all four trackable statuses |
| `tests/Feature/Tracking/TrackingFormTest.php` | Form rendering, session redirect, stale-value cleanup |
| `tests/Feature/Tracking/TrackingAuthenticateTest.php` | Success flow, session regeneration, generic-error uniformity, draft rejection, input echo policy |
| `tests/Feature/Tracking/TrackingStatusTest.php` | Snapshot rendering, timeline, courier-only-in-transit, no map / no websocket / no admin fields, guest access |
| `tests/Feature/Tracking/TrackingSignOutTest.php` | Session clear, CSRF rotation, idempotency, no side effects on the delivery |
| `tests/Feature/Tracking/TrackingThrottleTest.php` | 10-attempt window, throttled response, limiter reset, GET immunity |
