# ADR-016: Customer Tracking Authentication (Packet 10 Implementation)

- Status: Accepted
- Date: 2026-07-30
- Task Packet: 10
- Related: ADR-004 (early proposal),
  ADR-011 (delivery snapshots and receipt),
  AR-07, AR-42 (revised), AR-43, AR-44, AR-45, AR-46

## Context

ADR-004 recorded the intent to authenticate tracking access with a
receipt number plus the last four digits of the customer phone, with
a generic-error contract and rate limiting deferred. Packet 10
implemented that surface end-to-end. This ADR records what was
actually built, and locks the specific design choices ratified by
the Project Manager as AR-42 (revised) through AR-46.

The alternatives the Project Manager considered and rejected before
approving the design were:

1. **Signed URL emailed to the customer.** Rejected: no customer
   email is captured at scheduling, and the receipt is already the
   public artifact printed on the paper voucher.
2. **SMS-delivered one-time password.** Rejected: introduces a
   third-party dependency (SMS gateway), non-trivial per-message
   cost, and an operational failure mode (SMS not delivered) with no
   fallback path for the prototype.
3. **Magic-link over WhatsApp.** Rejected on the same third-party
   grounds plus platform lock-in.
4. **Full customer accounts.** Rejected as far outside prototype
   scope; a lookup-by-receipt experience is sufficient.
5. **Receipt number alone.** Rejected: the receipt is printed and
   could be photographed by anyone; a second factor bound to the
   order is required to keep casual lookup honest.

## Decision

### Two-factor lookup on a public form

Customers enter two values on a single public form:

- **Receipt number** issued at scheduling (Packet 07), canonical
  shape `DEL-YYYYMMDD-XXXX`.
- **Last four digits** of the phone number captured in the delivery
  snapshot (frozen at scheduling per AR-26).

Both values are on the customer's paper or email receipt. Neither is
a cryptographic secret; together they act as a low-friction identity
check appropriate for the tracking scope agreed in AR-42 (revised).

### Uniform-failure contract

`App\Domain\Tracking\TrackingAuthenticator::attempt(receipt, phone4)`
returns the matching `Delivery` or `null`. Every failure mode
returns `null` and never throws:

- Malformed receipt (fails regex `/^DEL-\d{8}-[A-Z0-9]{4}$/` after
  normalization).
- Malformed phone-last-4 (not four digits).
- Unknown receipt.
- Receipt matches a `draft` delivery (AR-44).
- Correct receipt but wrong phone-last-4.
- Missing or empty snapshot phone.

Callers cannot distinguish "bad format" from "wrong digits" from
"unknown receipt" from the return value. `TrackingAuthenticateRequest`
collapses every rule failure across either field into one form-level
error keyed `form`, and the controller uses the same generic copy on
domain failure:

> Invalid receipt or phone digits. Please check and try again.

The receipt is echoed back as old input; the phone-last-4 is never
echoed back.

### Constant-time phone comparison

The final match uses `hash_equals` between the four-digit slice of
the snapshot phone and the submitted digits. This yields
constant-time semantics and communicates intent even though the
inputs are short.

### Session-scoped access with fixation defense

On successful `attempt()`:

- `session()->regenerate()` is called (session-id fixation defense).
- `session(['tracking.delivery_id' => $delivery->id])` records the
  authenticated delivery.

`status()` reads that key on every render, and silently clears it if
the value is missing, non-numeric, points to a deleted delivery, or
points to a `draft` (AR-44). `signOut()` forgets the key and rotates
the CSRF token via `regenerateToken()`.

The session persists until browser close; there is no explicit
lifetime override. A "Look up another delivery" link on the status
page invokes `signOut()` and returns to the form.

### Static content, no live surfaces

The status page is static (AR-45). No polling, no WebSocket, no SSE,
no meta-refresh, no client-side JS timer, no Leaflet map. The Blade
view is asserted not to reference `leaflet`, `websocket`,
`setInterval`, or `pusher`. Customers reload the page manually. This
constraint is preserved until telemetry lands (Packet 11+).

### Courier disclosure only during in-transit

Courier name and phone are rendered on the status page **only** when
`status === 'in_transit'` (AR-45). In `scheduled`, `delivered`, and
`cancelled` the courier block is completely absent from the DOM, not
merely CSS-hidden.

### Basic route-level throttle

`POST /track` uses the framework's built-in `throttle:10,15` — ten
attempts per fifteen minutes per route + IP signature. No custom
named rate limiter was registered. AR-42 (revised) explicitly
approved the plain throttle over a custom limiter for the prototype
phase. Both `GET` routes are unthrottled so an honest customer can
retry after a bad tap.

### Public layout isolation

`resources/views/layouts/public.blade.php` is a separate minimal
layout containing only the tracking chrome. It never renders the
authenticated app header, so no admin link, session banner, or
sign-out row can leak onto a public page. The layout emits
`<meta name="robots" content="noindex, nofollow">` so search engines
do not surface individual tracking pages.

## Consequences

### Positive

- Zero-account customer lookup ships without any third-party
  dependency (no SMS gateway, no email provider, no OTP service).
- The uniform-failure contract eliminates the receipt-enumeration
  oracle that any distinct error message would introduce.
- The frozen snapshot from AR-26 is now doing double duty as the
  auth factor; no additional PII columns are required for tracking.
- The session-based access model is small enough to reason about
  without formal token expiry semantics; browser close is a
  well-understood boundary.

### Negative

- The paper receipt is the sole authentication artifact. A shared
  photo of it exposes the delivery to anyone with the customer's
  phone number - a small, accepted risk for a delivery prototype.
- Framework throttle keys on route + IP; carrier-grade NAT can
  aggregate legitimate customers under a single limiter bucket. If
  this bites in production, an explicit named limiter can be added
  without any change to the domain service.
- The status page is static. Customers watching a delivery in
  transit must refresh manually. Deliberate deferral until
  telemetry ingestion (Packet 11) lands.

## Compliance surface

- **AR-07:** receipt + phone-last-4 factors, implemented as
  described.
- **AR-42 (revised):** `hash_equals`, generic-error uniformity,
  session-id regeneration, `throttle:10,15`, no custom limiter.
- **AR-43:** session-scoped access with regeneration and sign-out.
- **AR-44:** draft deliveries excluded from lookup.
- **AR-45:** courier disclosure only during `in_transit`; static
  status page.
- **AR-46:** no live map, no polling, no WebSocket, no telemetry
  bridge, no API surface introduced.

## Alternatives considered

Enumerated under Context above. All were rejected before
implementation began. Nothing in Packet 10 forecloses future
iteration - the authenticator is a small stateless service and the
form request is a two-field DTO, both easy to replace or extend.
