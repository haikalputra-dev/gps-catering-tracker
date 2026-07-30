# Courier Visibility and Fee Privacy

The courier role is deliberately narrow. Couriers see what they
need to do their job — addresses, receipts, state, and the two
action buttons — and nothing else. In particular, they never see
the per-delivery fee, the distance breakdown, or the aggregate
delivery listing. This document specifies what a courier can and
cannot see, and where those constraints are enforced. See AR-40
and AR-41 for the decisions.

## The visibility matrix

| Surface                                | Owner  | Staff  | Assigned courier | Other courier |
| -------------------------------------- | ------ | ------ | ---------------- | ------------- |
| `GET /deliveries` (index)              | yes    | yes    | no (403)         | no (403)      |
| `GET /deliveries/create`               | yes    | yes    | no (403)         | no (403)      |
| `GET /deliveries/{id}/edit`            | yes    | yes    | no (403)         | no (403)      |
| `GET /deliveries/{id}` (show)          | yes    | yes    | yes              | no (redirect) |
| `POST /deliveries/{id}/dispatch`       | no     | no     | yes              | no            |
| `POST /deliveries/{id}/mark-delivered` | no     | no     | yes              | no            |
| `POST /deliveries/{id}/cancel`         | yes    | yes    | own in_transit only | no          |
| `GET /courier/dashboard`               | no (403) | no (403) | yes            | yes           |

The dashboard is the courier's home surface. The show page is the
detail view for a delivery they are actively responsible for.
Everything else is office-only.

## Fee privacy invariant (AR-40)

On any surface the courier can reach, the following values MUST NOT
appear in the response body:

- `fee_rupiah` (raw or formatted, including the "Rp X.XXX" pattern)
- `distance_km` (the numeric distance)
- The "Pricing" section header on the show page
- The "Distance:" label on the show page
- The "Fee" column on the dashboard

The invariant is enforced by conditional Blade rendering: the
Pricing card on the show page is wrapped in an
`@if($isOfficeUser) ... @endif` block, and the courier dashboard
template does not render a fee column at all.

`DeliveryFeePrivacyForCourierTest` locks this invariant:

- Assigned courier viewing show: no `Pricing`, no `Distance:`, no
  formatted fee, but the receipt number and addresses ARE visible.
- Assigned courier viewing dashboard: no formatted fee, no `Fee`
  label.
- Owner viewing show: fee IS visible (contrast case).
- Staff viewing show: fee IS visible (contrast case).

## Show-page semantics for couriers

An assigned courier hitting `GET /deliveries/{id}` sees:

- Kitchen name, address, and coordinates
- Customer name, address, and coordinates
- Receipt number
- Status pill (current state)
- Scheduled-at, dispatched-at, delivered-at (where set)
- Cancellation reason (if cancelled)
- State-appropriate action buttons (`Start Delivery` on scheduled,
  `Mark Delivered` on in_transit, `Cancel` on in_transit if it's
  their own)
- The map with the two markers

They do NOT see:

- Pricing card, distance readout, fee value
- Edit or schedule buttons (those are office-only)

A non-assigned courier hitting the same URL is redirected to the
courier dashboard with a session error under the `status` key.
They cannot even confirm the delivery exists by URL guessing.

## Dashboard semantics

`GET /courier/dashboard` renders:

- A greeting card
- Either an empty-state (`No active delivery`) OR one active
  delivery card containing:
  - Receipt number
  - Kitchen and customer addresses
  - The state pill
  - The state-appropriate action button

The dashboard query scopes to:

- `courier_id = auth()->id()`
- `status IN ('scheduled', 'in_transit')`

Terminal states (`delivered`, `cancelled`) are explicitly excluded
so the dashboard is a "what should I be doing right now?" surface
rather than a career-history view.

## Enforcement points

| Concern                          | Enforced at                                          |
| -------------------------------- | ---------------------------------------------------- |
| Role-based route access          | `role:owner,staff` and `role:courier` middleware.    |
| Assigned-courier show check      | `DeliveryController::show()`.                        |
| Assigned-courier cancel check    | `CancelDeliveryRequest::authorize()`.                |
| Dashboard scope filtering        | `DashboardController::courier()` query.              |
| Fee privacy on show              | Blade `@if` in the show template.                    |
| Fee absence on dashboard         | Blade template does not reference `fee_rupiah`.      |

Middleware handles the coarse role split. FormRequests handle the
per-instance authorization. Blade handles the per-field visibility.
Each layer has one job.

## Why the courier is not a "read-only user"

The courier role is not a general read-only surface for the
delivery system. It is a specific operational surface for a
specific person. Two consequences:

1. There is no "list of my past deliveries" view. If a courier
   needs to look at a historical delivery, they ask the office;
   the office pulls it up on the index and shares the details.
   This is intentional prototype scope.

2. The courier has no visibility into other couriers' work. Their
   dashboard is scoped to themselves only. This preserves a small
   amount of privacy between couriers and prevents an "I see you
   have three deliveries today" comparison surface.

Both restrictions can be revisited in a future packet. AR-41
explicitly excluded them from this one.
