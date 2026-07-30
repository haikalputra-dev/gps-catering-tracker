<?php

declare(strict_types=1);

namespace App\Domain\Delivery;

use App\Domain\Delivery\Exceptions\ConcurrencyLimitReachedException;
use App\Domain\Delivery\Exceptions\CourierConcurrencyLimitReachedException;
use App\Domain\Delivery\Exceptions\CourierNotCourierRoleException;
use App\Domain\Delivery\Exceptions\InactiveCourierException;
use App\Domain\Delivery\Exceptions\InactiveCustomerException;
use App\Domain\Delivery\Exceptions\InactiveKitchenException;
use App\Domain\Delivery\Exceptions\MissingCourierException;
use App\Domain\Delivery\Exceptions\MissingSchedulingFieldsException;
use App\Domain\Delivery\Exceptions\NotSchedulableStateException;
use App\Domain\Identity\UserRole;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Kitchen;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Transitions a draft delivery to `scheduled`, capturing snapshots and
 * assigning a receipt number atomically inside a single database transaction.
 *
 * All invariants (AR-23 state machine, AR-24 receipt, AR-25 snapshots,
 * AR-27 concurrency, AR-28 UTC storage) are enforced here. The controller
 * layer is a thin adapter; the FormRequest normalises input; this service
 * is the sole authority on scheduling.
 */
class DeliveryScheduler
{
    public function __construct(
        private readonly ReceiptNumberGenerator $receipts,
        private readonly DistanceCalculator $distances,
        private readonly PricingCalculator $pricing,
    ) {
    }

    /**
     * Schedule the given draft delivery.
     *
     * Preconditions:
     *   - $delivery->status === draft
     *   - $delivery->kitchen_id references an active kitchen
     *   - $delivery->customer_id references an active customer
     *   - $delivery->scheduled_at is set and in the future
     *   - $delivery->courier_id references an active user with role Courier
     *     whose active-workload count is below the per-courier cap (AR-37)
     *   - Non-terminal delivery count is below the configured cap
     *
     * On success the delivery is refreshed in place with:
     *   - status = scheduled
     *   - receipt_number generated
     *   - kitchen_* and customer_* snapshot columns populated
     *   - scheduled_by_user_id + scheduled_at_recorded audit trail
     */
    public function schedule(Delivery $delivery, User $actor): Delivery
    {
        if ($delivery->status !== DeliveryStatus::Draft) {
            throw NotSchedulableStateException::forStatus($delivery->status->value);
        }

        $this->assertSchedulingFields($delivery);

        return DB::transaction(function () use ($delivery, $actor): Delivery {
            $fresh = Delivery::query()
                ->whereKey($delivery->getKey())
                ->lockForUpdate()
                ->first();

            if ($fresh === null) {
                throw NotSchedulableStateException::forStatus('missing');
            }

            if ($fresh->status !== DeliveryStatus::Draft) {
                throw NotSchedulableStateException::forStatus($fresh->status->value);
            }

            $this->assertSchedulingFields($fresh);

            $kitchen = Kitchen::query()->lockForUpdate()->findOrFail($fresh->kitchen_id);
            if (! $kitchen->is_active) {
                throw InactiveKitchenException::forKitchenId((int) $kitchen->getKey());
            }

            $customer = Customer::query()->lockForUpdate()->findOrFail($fresh->customer_id);
            if (! $customer->is_active) {
                throw InactiveCustomerException::forCustomerId((int) $customer->getKey());
            }

            $courier = $this->assertCourier($fresh);
            $this->assertCourierConcurrencyLimit((int) $courier->getKey(), $fresh->getKey());

            $this->assertConcurrencyLimit($fresh->getKey());

            // Compute Haversine distance from the snapshot coordinates
            // (AR-25, AR-32). Reading directly from the locked kitchen
            // and customer rows guarantees the priced values match the
            // snapshot values byte-for-byte. Storage rounding to 3 dp
            // aligns with the decimal(8, 3) column (AR-31).
            $distanceKm = $this->distances->between(
                (float) $kitchen->latitude,
                (float) $kitchen->longitude,
                (float) $customer->latitude,
                (float) $customer->longitude,
            );
            $distanceKm = round($distanceKm, 3, PHP_ROUND_HALF_UP);

            $feeRupiah = $this->pricing->feeForDistanceKm($distanceKm);

            $scheduledAt = $fresh->scheduled_at;
            $receipt = $this->receipts->generate($scheduledAt);
            $now = Carbon::now('UTC');

            $fresh->forceFill([
                'receipt_number' => $receipt,
                'status' => DeliveryStatus::Scheduled,
                'kitchen_code' => $kitchen->code,
                'kitchen_name' => $kitchen->name,
                'kitchen_address' => $kitchen->address,
                'kitchen_latitude' => $kitchen->latitude,
                'kitchen_longitude' => $kitchen->longitude,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'customer_address' => $customer->address,
                'customer_latitude' => $customer->latitude,
                'customer_longitude' => $customer->longitude,
                'distance_km' => $distanceKm,
                'fee_rupiah' => $feeRupiah,
                'scheduled_by_user_id' => $actor->getKey(),
                'scheduled_at_recorded' => $now,
            ])->save();

            return $fresh->refresh();
        });
    }

    /**
     * Assert the draft has the fields required for scheduling.
     */
    private function assertSchedulingFields(Delivery $delivery): void
    {
        if ($delivery->kitchen_id === null) {
            throw MissingSchedulingFieldsException::forField('kitchen_id');
        }

        if ($delivery->customer_id === null) {
            throw MissingSchedulingFieldsException::forField('customer_id');
        }

        if ($delivery->scheduled_at === null) {
            throw MissingSchedulingFieldsException::forField('scheduled_at');
        }

        if (Carbon::instance($delivery->scheduled_at)->lessThanOrEqualTo(Carbon::now('UTC'))) {
            throw MissingSchedulingFieldsException::forField('scheduled_at');
        }
    }

    /**
     * Load and validate the courier assigned to the fresh delivery row.
     *
     * Preconditions checked here (AR-37):
     *   - `courier_id` is set on the delivery row.
     *   - The referenced user row exists.
     *   - The user has role Courier.
     *   - The user is active.
     *
     * The courier row is locked for update inside the transaction alongside
     * the kitchen and customer rows so that a concurrent deactivation or
     * role change cannot slip between validation and the scheduling write.
     */
    private function assertCourier(Delivery $delivery): User
    {
        if ($delivery->courier_id === null) {
            throw MissingCourierException::missing();
        }

        $courier = User::query()->lockForUpdate()->find($delivery->courier_id);

        if ($courier === null) {
            throw MissingCourierException::forCourierId((int) $delivery->courier_id);
        }

        if ($courier->role !== UserRole::Courier) {
            throw CourierNotCourierRoleException::forCourierId((int) $courier->getKey());
        }

        if (! $courier->is_active) {
            throw InactiveCourierException::forCourierId((int) $courier->getKey());
        }

        return $courier;
    }

    /**
     * Reject the scheduling attempt if the per-courier concurrency cap is
     * already met (AR-34). Only `scheduled` and `in_transit` deliveries
     * assigned to the same courier count; drafts are excluded because they
     * have no committed courier assignment yet per AR-37.
     *
     * The current delivery is excluded from the count because it will
     * occupy one of the courier's slots once the transition completes.
     */
    private function assertCourierConcurrencyLimit(int $courierId, int|string $currentDeliveryId): void
    {
        $limit = (int) config('delivery.max_concurrent_per_courier', 1);

        if ($limit <= 0) {
            throw CourierConcurrencyLimitReachedException::forLimit($limit);
        }

        $activeCount = Delivery::query()
            ->where('courier_id', $courierId)
            ->whereIn('status', [
                DeliveryStatus::Scheduled->value,
                DeliveryStatus::InTransit->value,
            ])
            ->whereKeyNot($currentDeliveryId)
            ->count();

        if ($activeCount >= $limit) {
            throw CourierConcurrencyLimitReachedException::forLimit($limit);
        }
    }

    /**
     * Reject the scheduling attempt if the concurrency cap is already met.
     *
     * The current delivery is excluded from the count because it will occupy
     * one of the slots when the transition completes.
     */
    private function assertConcurrencyLimit(int|string $currentDeliveryId): void
    {
        $limit = (int) config('delivery.max_concurrent_active', 1);

        if ($limit <= 0) {
            throw ConcurrencyLimitReachedException::forLimit($limit);
        }

        $activeCount = Delivery::query()
            ->active()
            ->whereKeyNot($currentDeliveryId)
            ->count();

        if ($activeCount >= $limit) {
            throw ConcurrencyLimitReachedException::forLimit($limit);
        }
    }
}
