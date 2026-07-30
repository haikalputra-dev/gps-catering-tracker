# Delivery Management

Delivery orders are the operational unit of the catering tracker. A
delivery represents one prepared meal shipment from a kitchen to a
customer. This document describes the delivery lifecycle from the
operator's perspective. For deeper coverage see:

- [Delivery state machine](delivery-state-machine.md)
- [Receipt numbers](receipt-numbers.md)
- [Snapshots and history](snapshots-and-history.md)
- [Concurrency limit](concurrency-limit.md)

## Who can manage deliveries

Access is restricted to `owner` and `staff` roles. Couriers cannot yet
view or modify deliveries; the courier surface (assignment, tracking)
arrives in later packets. Guests are redirected to `/login`.

## Available actions

The eight routes registered under `/deliveries` are:

| Method | URI                                | Name                 | Purpose                       |
| ------ | ---------------------------------- | -------------------- | ----------------------------- |
| GET    | `/deliveries`                      | `deliveries.index`   | Paginated list with filters   |
| GET    | `/deliveries/create`               | `deliveries.create`  | New draft form                |
| POST   | `/deliveries`                      | `deliveries.store`   | Persist a new draft           |
| GET    | `/deliveries/{delivery}`           | `deliveries.show`    | Detail view                   |
| GET    | `/deliveries/{delivery}/edit`      | `deliveries.edit`    | Edit form (drafts only)       |
| PUT    | `/deliveries/{delivery}`           | `deliveries.update`  | Persist draft edits           |
| POST   | `/deliveries/{delivery}/schedule`  | `deliveries.schedule`| Draft to scheduled            |
| POST   | `/deliveries/{delivery}/cancel`    | `deliveries.cancel`  | Draft/scheduled to cancelled  |

No `DELETE` route is registered; deliveries are never destroyed. No API
route is exposed in Packet 07.

## Creating a draft

`GET /deliveries/create` renders a form asking for kitchen, customer,
scheduled time (optional at this stage), and free-form notes. On submit
the delivery is stored with `status = draft` and audit field
`created_by_user_id` set to the acting user. No receipt number is
generated yet and no snapshots are captured. Draft rows are safe to
edit or discard.

## Editing a draft

Only drafts are editable. Attempting to open the edit form on a
delivery in any other status redirects to the show page with an inline
error. This constraint is enforced at three layers:

- `UpdateDeliveryRequest::authorize()` returns `false` unless
  `status === draft`
- `DeliveryController::edit()` guards the view
- The delivery detail view suppresses the Edit button when the delivery
  is not a draft

## Listing and filtering

`GET /deliveries` returns the paginated list (15 per page). The status
filter is a simple query string, `?status=scheduled` for example. The
ordering places non-terminal statuses first, then rows without a
scheduled time, then earliest scheduled first, then newest created
first. This produces a work queue where actionable deliveries surface
at the top and terminal history sinks to the bottom.

## Cancellation

Both drafts and scheduled deliveries may be cancelled. A cancellation
reason of 3 to 255 characters is required. Scheduled cancellations
preserve the receipt number and all snapshot data so that historical
records remain complete. See
[snapshots-and-history.md](snapshots-and-history.md) for the immutability
guarantee.

## Distance and fee (Packet 08)

Scheduling now also freezes a straight-line Haversine `distance_km`
and a rupiah `fee_rupiah` on the delivery row. Both use the snapshot
coordinates captured at scheduling and are preserved on cancellation.
See [pricing-and-distance.md](pricing-and-distance.md).

## Out of scope

- Courier assignment or `in_transit` / `delivered` transitions
- Fee recalculation, discounts, taxes, surcharges
- Routing distance (only geodesic Haversine)
- Receipt-number public tracking page or authorization tokens
- SMS notifications, GPS telemetry, firmware integration
- API endpoints, mobile surfaces, WebSocket updates

These arrive in later packets.
