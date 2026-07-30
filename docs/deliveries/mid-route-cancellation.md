# Mid-Route Cancellation

Packet 07 introduced cancellation from `draft` and `scheduled`.
Packet 09 extends the cancellable set to include `in_transit`, so
the office and the assigned courier both have an escape hatch when
something goes wrong on the road. This document explains WHO can
cancel WHAT and WHY. See ADR-010 for the state machine and AR-38
(revised) for the decision.

## The revised cancellation matrix

| Source state | Owner  | Staff  | Assigned courier | Other courier |
| ------------ | ------ | ------ | ---------------- | ------------- |
| `draft`      | yes    | yes    | no               | no            |
| `scheduled`  | yes    | yes    | no               | no            |
| `in_transit` | yes    | yes    | yes (own only)   | no            |
| `delivered`  | no     | no     | no               | no            |
| `cancelled`  | no     | no     | no               | no            |

Two rules capture the entire matrix:

1. **Owner and staff** may cancel any non-terminal delivery. Their
   authority is scoped by the office, not by which courier is
   assigned.
2. **A courier** may cancel only a delivery that is currently
   `in_transit` AND whose `courier_id` equals their own user id.
   Couriers cannot cancel drafts or scheduled deliveries at all
   (those are office-owned surfaces), and cannot cancel another
   courier's route.

Terminal states remain terminal. Once `delivered` or `cancelled`,
no further transition is possible.

## Where the rule lives

The rule is enforced in two places for defence in depth:

1. `CancelDeliveryRequest::authorize()` — the FormRequest short-
   circuits at the HTTP boundary. If the actor is not authorised for
   this specific delivery in its current state, the request's
   `failedAuthorization()` throws an `HttpResponseException` with a
   redirect to a role-appropriate surface and a session error under
   the `status` key. This is why tests assert
   `assertRedirect()->assertSessionHasErrors('status')` rather than
   `assertForbidden()` — the FormRequest never lets a truly forbidden
   request reach the controller.

2. `DeliveryCanceller::cancel()` — the domain service performs the
   same authorization check independently before writing anything.
   This lets the service be called from an artisan command, a
   future API, or a test without duplicating the check in every
   caller. Unauthorised calls raise
   `NotAuthorizedToCancelException`.

Belt-and-braces: if a bug ever loosens the FormRequest, the domain
service still refuses. If a caller bypasses the FormRequest, the
domain service still refuses.

## Cancellation always requires a reason

Regardless of state or actor, a non-empty `cancellation_reason` is
required. Blank or whitespace-only reasons raise
`CancellationReasonRequiredException` (Packet 07 behaviour,
preserved). This keeps the audit trail useful — a cancelled
in-transit delivery without a reason would be an operational
mystery.

## What cancellation preserves

Cancelling from `in_transit` does NOT modify:

- `courier_id` — the responsible courier stays attached.
- `dispatched_at` — the moment the food actually left the kitchen.
- All snapshot columns (`kitchen_*`, `customer_*`, `receipt_number`,
  `distance_km`, `fee_rupiah`).

Cancellation only writes:

- `status = 'cancelled'`
- `cancellation_reason = <supplied reason>`
- `cancelled_at = Carbon::now('UTC')`
- `cancelled_by_user_id = <actor id>`

`delivered_at` remains null, because a cancelled-mid-route delivery
was never delivered.

## Fee handling

The fee is preserved on cancellation for reporting continuity —
this matches Packet 08 behaviour and is unchanged. Whether the
customer is actually charged is an office-level decision made
outside the system.

## Rationale

The pre-Packet-09 rule (`in_transit` was terminal for cancellation)
mismatched physical reality: sometimes traffic, customer no-show,
or a broken vehicle makes an in-transit delivery impossible to
complete. Forcing the office to wait for a phantom `delivered`
transition, or worse, mark it delivered when it wasn't, would
corrupt the audit trail.

Allowing the assigned courier to cancel their own in-transit
delivery gives them a way to close the loop from the field. They
know first when a route is failing.

Restricting the mid-route cancel to the assigned courier prevents
one courier from meddling with another courier's active route,
which would be nonsensical from the physical workflow.

## Error surface

| Exception                              | Trigger                                                      |
| -------------------------------------- | ------------------------------------------------------------ |
| `NotAuthorizedToCancelException`       | Actor not permitted for this delivery/state (see matrix).    |
| `NotCancellableStateException`         | Source state is `delivered` or `cancelled` (terminal).       |
| `CancellationReasonRequiredException`  | Missing or blank `cancellation_reason`.                      |

All three are typed exceptions in `App\Domain\Delivery\Exceptions`.
The controller catches them and returns the operator to the show
page with a session error.
