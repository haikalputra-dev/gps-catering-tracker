<?php

declare(strict_types=1);

namespace App\Domain\Delivery;

use App\Domain\Delivery\Exceptions\InactiveCourierException;
use App\Domain\Delivery\Exceptions\NotAssignedCourierException;
use App\Domain\Delivery\Exceptions\NotCompletableStateException;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Transitions an in-transit delivery to `delivered` on behalf of the
 * assigned courier (AR-35, AR-41).
 *
 * Deliberately minimal: the courier taps a "Mark Delivered" button and
 * the domain service records `delivered_at`. No auto-detection, no GPS
 * proximity check, no customer confirmation. All actor-identity and
 * state-machine invariants are enforced inside a single transaction
 * with `lockForUpdate` on the delivery row.
 *
 * The completer does NOT modify kitchen, customer, snapshot, distance,
 * fee, receipt, courier or dispatched_at fields. It only sets
 * `delivered_at` and transitions `status` from `in_transit` to
 * `delivered`.
 */
class DeliveryCompleter
{
    /**
     * Mark the given in-transit delivery as delivered.
     *
     * Preconditions:
     *   - $delivery->status === in_transit
     *   - $actor->id === $delivery->courier_id
     *   - $actor->is_active is true (defensive; middleware enforces this)
     *
     * On success the delivery is refreshed in place with:
     *   - status = delivered
     *   - delivered_at = now (UTC), guaranteed >= dispatched_at
     */
    public function complete(Delivery $delivery, User $actor): Delivery
    {
        return DB::transaction(function () use ($delivery, $actor): Delivery {
            $fresh = Delivery::query()
                ->whereKey($delivery->getKey())
                ->lockForUpdate()
                ->first();

            if ($fresh === null) {
                throw NotCompletableStateException::forStatus('missing');
            }

            if ($fresh->status !== DeliveryStatus::InTransit) {
                throw NotCompletableStateException::forStatus($fresh->status->value);
            }

            if ((int) $fresh->courier_id !== (int) $actor->getKey()) {
                throw NotAssignedCourierException::forCourierId((int) $actor->getKey());
            }

            if (! $actor->is_active) {
                throw InactiveCourierException::forCourierId((int) $actor->getKey());
            }

            $fresh->forceFill([
                'status' => DeliveryStatus::Delivered,
                'delivered_at' => Carbon::now('UTC'),
            ])->save();

            return $fresh->refresh();
        });
    }
}
