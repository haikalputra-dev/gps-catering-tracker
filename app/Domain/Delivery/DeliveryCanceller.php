<?php

declare(strict_types=1);

namespace App\Domain\Delivery;

use App\Domain\Delivery\Exceptions\CancellationReasonRequiredException;
use App\Domain\Delivery\Exceptions\NotCancellableStateException;
use App\Models\Delivery;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Transitions a delivery from `draft` or `scheduled` to `cancelled`.
 *
 * Cancellation preserves the receipt number and snapshots on `scheduled`
 * deliveries; drafts have neither yet, so those fields simply remain null.
 * The reason must already be trimmed to 3..255 characters by the caller
 * (the FormRequest layer handles this); the service performs a defensive
 * re-check and rejects invalid input with a dedicated exception.
 */
class DeliveryCanceller
{
    /**
     * Minimum acceptable trimmed reason length (matches FormRequest rule).
     */
    public const REASON_MIN_LENGTH = 3;

    /**
     * Maximum acceptable trimmed reason length (matches DB column width).
     */
    public const REASON_MAX_LENGTH = 255;

    /**
     * Cancel the given delivery.
     *
     * On success the delivery is refreshed in place with:
     *   - status = cancelled
     *   - cancellation_reason = $reason (trimmed)
     *   - cancelled_by_user_id + cancelled_at audit trail
     *
     * Receipt number and snapshots (if any) are preserved verbatim.
     */
    public function cancel(Delivery $delivery, User $actor, string $reason): Delivery
    {
        $trimmed = trim($reason);

        if (
            $trimmed === ''
            || mb_strlen($trimmed) < self::REASON_MIN_LENGTH
            || mb_strlen($trimmed) > self::REASON_MAX_LENGTH
        ) {
            throw CancellationReasonRequiredException::missing();
        }

        if (! $delivery->status->canTransitionTo(DeliveryStatus::Cancelled)) {
            throw NotCancellableStateException::forStatus($delivery->status->value);
        }

        return DB::transaction(function () use ($delivery, $actor, $trimmed): Delivery {
            $fresh = Delivery::query()
                ->whereKey($delivery->getKey())
                ->lockForUpdate()
                ->first();

            if ($fresh === null) {
                throw NotCancellableStateException::forStatus('missing');
            }

            if (! $fresh->status->canTransitionTo(DeliveryStatus::Cancelled)) {
                throw NotCancellableStateException::forStatus($fresh->status->value);
            }

            $fresh->forceFill([
                'status' => DeliveryStatus::Cancelled,
                'cancellation_reason' => $trimmed,
                'cancelled_by_user_id' => $actor->getKey(),
                'cancelled_at' => Carbon::now('UTC'),
            ])->save();

            return $fresh->refresh();
        });
    }
}
