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
theoretically valid. Packet 07 implements only a subset of these; the
remaining transitions are wired in later packets.

| From        | To          | Implemented in Packet 07 |
| ----------- | ----------- | ------------------------ |
| `draft`     | `scheduled` | Yes                      |
| `draft`     | `cancelled` | Yes                      |
| `scheduled` | `cancelled` | Yes                      |
| `scheduled` | `in_transit`| No (declared only)       |
| `in_transit`| `delivered` | No (declared only)       |

Terminal states have no outgoing transitions. Once a delivery is
`delivered` or `cancelled` it stays there forever.

## Authoritative services

Business logic never lives in the controller. Two domain services own
the transitions:

- `App\Domain\Delivery\DeliveryScheduler` handles `draft to scheduled`.
- `App\Domain\Delivery\DeliveryCanceller` handles the two cancellation
  paths.

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

## Preconditions for cancellation

The canceller enforces:

1. `status in (draft, scheduled)`
2. `cancellation_reason` trimmed length in `[3, 255]`

Snapshots and receipt numbers already recorded are preserved. Drafts
have no snapshot data to preserve.

## Exception mapping

| Exception                              | Meaning                              |
| -------------------------------------- | ------------------------------------ |
| `NotSchedulableStateException`         | Not in `draft`                       |
| `MissingSchedulingFieldsException`     | Required field missing or invalid    |
| `InactiveKitchenException`             | Kitchen `is_active = false`          |
| `InactiveCustomerException`            | Customer `is_active = false`         |
| `ConcurrencyLimitReachedException`     | Cap already met                      |
| `NotCancellableStateException`         | Already terminal or wrong status     |
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
