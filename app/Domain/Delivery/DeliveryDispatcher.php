<?php

declare(strict_types=1);

namespace App\Domain\Delivery;

use App\Domain\Delivery\Exceptions\InactiveCourierException;
use App\Domain\Delivery\Exceptions\MissingCourierException;
use App\Domain\Delivery\Exceptions\NotAssignedCourierException;
use App\Domain\Delivery\Exceptions\NotDispatchableStateException;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Transitions a scheduled delivery to `in_transit` on behalf of the
 * assigned courier (AR-41).
 *
 * All invariants (state machine, actor-identity, timestamp) are enforced
 * here inside a single database transaction with `lockForUpdate` on the
 * delivery row so concurrent dispatch attempts collapse to exactly one
 * successful transition.
 *
 * The dispatcher does NOT modify kitchen, customer, snapshot, distance,
 * fee, receipt or courier fields. It only sets `dispatched_at` and
 * transitions `status` from `scheduled` to `in_transit`.
 */
class DeliveryDispatcher
{
    /**
     * Dispatch the given scheduled delivery.
     *
     * Preconditions:
     *   - $delivery->status === scheduled
     *   - $delivery->courier_id is set (defensive; must be set by AR-37)
     *   - $actor->id === $delivery->courier_id
     *   - $actor->is_active is true (defensive; middleware enforces this)
     *
     * On success the delivery is refreshed in place with:
     *   - status = in_transit
     *   - dispatched_at = now (UTC)
     */
    public function dispatch(Delivery $delivery, User $actor): Delivery
    {
        return DB::transaction(function () use ($delivery, $actor): Delivery {
            $fresh = Delivery::query()
                ->whereKey($delivery->getKey())
                ->lockForUpdate()
                ->first();

            if ($fresh === null) {
                throw NotDispatchableStateException::forStatus('missing');
            }

            if ($fresh->status !== DeliveryStatus::Scheduled) {
                throw NotDispatchableStateException::forStatus($fresh->status->value);
            }

            if ($fresh->courier_id === null) {
                throw MissingCourierException::missing();
            }

            if ((int) $fresh->courier_id !== (int) $actor->getKey()) {
                throw NotAssignedCourierException::forCourierId((int) $actor->getKey());
            }

            if (! $actor->is_active) {
                throw InactiveCourierException::forCourierId((int) $actor->getKey());
            }

            $fresh->forceFill([
                'status' => DeliveryStatus::InTransit,
                'dispatched_at' => Carbon::now('UTC'),
            ])->save();

            return $fresh->refresh();
        });
    }
}
