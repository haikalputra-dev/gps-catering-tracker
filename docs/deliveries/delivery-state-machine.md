# Delivery State Machine

Delivery orders progress through a strict finite state machine. This
document specifies the states, transitions, and invariants exercised
in Packet 07. Future packets add more transitions but never rewrite
existing ones.

## States

| State       | Editable | Counts toward concurrency cap | Terminal |
| ----------- | -------- | ----------------------------- | -------- |
| `draft`     | Yes      | Yes                           | No       |
| `scheduled` | No       | Yes                           | No       |
| `in_transit`| No       | Yes                           | No       |
| `delivered` | No       | No                            | Yes      |
| `cancelled` | No       | No                            | Yes      |

The enum lives at `app/Domain/Delivery/DeliveryStatus.php`. Truth tables
above are encoded in `isEditable()`, `isActiveForConcurrency()`, and
`isTerminal()` on the enum.

## Transitions allowed by the enum

`DeliveryStatus::canTransitionTo()` declares which transitions are
valid. Packet 09 completes the machine — every transition below is
now wired to a domain service and reachable through the HTTP layer.

| From         | To           | First implemented in |
| ------------ | ------------ | -------------------- |
| `draft`      | `scheduled`  | Packet 07            |
| `draft`      | `cancelled`  | Packet 07            |
| `scheduled`  | `cancelled`  | Packet 07            |
| `scheduled`  | `in_transit` | Packet 09            |
| `in_transit` | `delivered`  | Packet 09            |
| `in_transit` | `cancelled`  | Packet 09 (AR-38 revised) |

Terminal states have no outgoing transitions. Once a delivery is
`delivered` or `cancelled` it stays there forever.

## Authoritative services

Business logic never lives in the controller. Four domain services
own the transitions:

- `App\Domain\Delivery\DeliveryScheduler` handles `draft → scheduled`.
- `App\Domain\Delivery\DeliveryDispatcher` handles `scheduled →
  in_transit`.
- `App\Domain\Delivery\DeliveryCompleter` handles `in_transit →
  delivered`.
- `App\Domain\Delivery\DeliveryCanceller` handles the three
  cancellation paths (from draft, scheduled, and in_transit).

Each service:

- Runs inside a database transaction
- Locks the delivery row with `lockForUpdate()` to prevent races
- Verifies preconditions on the freshly locked copy, not the passed-in
  model
- Emits a specific typed exception on rejection so the controller can
  map errors to session flashes

## Preconditions for `draft to scheduled`

The scheduler enforces (in order):

1. `status === draft`
2. `kitchen_id`, `customer_id`, and `scheduled_at` are all present
3. `scheduled_at` is strictly in the future (UTC comparison)
4. The referenced kitchen is `is_active = true`
5. The referenced customer is `is_active = true`
6. The active delivery count (excluding this one) is below the cap

On success it captures snapshots, generates a receipt, records the
scheduler, and flips status atomically.

## Preconditions for `scheduled → in_transit`

`DeliveryDispatcher` enforces (in order):

1. `status === scheduled`
2. `courier_id` is non-null (should be, since the scheduler
   required it, but re-verified defensively)
3. Acting user's `id` equals `courier_id`
4. Acting courier is `is_active = true`

On success it writes `status = in_transit` and
`dispatched_at = Carbon::now('UTC')`. Snapshot columns are
untouched.

## Preconditions for `in_transit → delivered`

`DeliveryCompleter` enforces (in order):

1. `status === in_transit`
2. Acting user's `id` equals `courier_id`
3. Acting courier is `is_active = true`

On success it writes `status = delivered` and
`delivered_at = Carbon::now('UTC')`. `dispatched_at` is preserved.

## Preconditions for cancellation

The canceller enforces:

1. `status in (draft, scheduled, in_transit)` — terminal states are
   rejected with `NotCancellableStateException`
2. Actor is authorised for this state (owner/staff any non-terminal;
   assigned courier only their own `in_transit`; else
   `NotAuthorizedToCancelException`)
3. `cancellation_reason` non-empty after trimming

Snapshots, receipt numbers, `dispatched_at`, and `courier_id`
already recorded are preserved. Drafts have no snapshot data to
preserve.

See `docs/deliveries/mid-route-cancellation.md` for the full
cancellation matrix.

## Exception mapping

| Exception                              | Meaning                              |
| -------------------------------------- | ------------------------------------ |
| `NotSchedulableStateException`         | Not in `draft`                       |
| `MissingSchedulingFieldsException`     | Required field missing or invalid    |
| `InactiveKitchenException`             | Kitchen `is_active = false`          |
| `InactiveCustomerException`            | Customer `is_active = false`         |
| `ConcurrencyLimitReachedException`     | Global cap already met               |
| `MissingCourierException`              | `courier_id` null at scheduling      |
| `CourierNotCourierRoleException`       | Assigned user is not a courier       |
| `InactiveCourierException`             | Courier `is_active = false`          |
| `CourierConcurrencyLimitReachedException` | Per-courier cap already met       |
| `NotDispatchableStateException`        | Not in `scheduled` at dispatch time  |
| `NotCompletableStateException`         | Not in `in_transit` at complete time |
| `NotAssignedCourierException`          | Actor is not the assigned courier    |
| `NotCancellableStateException`         | Already terminal                     |
| `NotAuthorizedToCancelException`       | Actor not permitted for this state   |
| `CancellationReasonRequiredException`  | Reason missing or wrong length       |

All live under `App\Domain\Delivery\Exceptions`. The controller catches
them and redirects to the show page with a session error keyed on
`status`.

## Testing the state machine

State-machine correctness is exercised in:

- `tests/Unit/Domain/Delivery/DeliveryStatusTest.php`
- `tests/Unit/Domain/Delivery/DeliverySchedulerTest.php`
- `tests/Unit/Domain/Delivery/DeliveryCancellerTest.php`
- `tests/Feature/Delivery/DeliveryStateMachineTest.php`
