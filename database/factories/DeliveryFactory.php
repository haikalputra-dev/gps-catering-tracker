<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Delivery\DeliveryStatus;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Kitchen;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends Factory<Delivery>
 *
 * Factories use Faker only; no real personal data is ever seeded.
 * States mirror the transitions exercised in Packet 07:
 *   - default          -> draft
 *   - scheduled        -> scheduled (with snapshots + receipt)
 *   - cancelledFromDraft
 *   - cancelledFromScheduled
 */
class DeliveryFactory extends Factory
{
    protected $model = Delivery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => DeliveryStatus::Draft->value,
            'kitchen_id' => Kitchen::factory(),
            'customer_id' => Customer::factory(),
            'scheduled_at' => null,
            'notes' => $this->faker->optional(0.3)->sentence(),
            'receipt_number' => null,
            'kitchen_code' => null,
            'kitchen_name' => null,
            'kitchen_address' => null,
            'kitchen_latitude' => null,
            'kitchen_longitude' => null,
            'customer_name' => null,
            'customer_phone' => null,
            'customer_address' => null,
            'customer_latitude' => null,
            'customer_longitude' => null,
            'scheduled_by_user_id' => null,
            'scheduled_at_recorded' => null,
            'cancellation_reason' => null,
            'cancelled_by_user_id' => null,
            'cancelled_at' => null,
            'created_by_user_id' => User::factory(),
        ];
    }

    /**
     * State: a delivery already transitioned to `scheduled` with snapshot data.
     *
     * Uses deterministic snapshot values so tests can assert against them.
     * A synthetic receipt suffix is generated inline to avoid depending on
     * the runtime ReceiptNumberGenerator (which is exercised elsewhere).
     */
    public function scheduled(): static
    {
        return $this->state(function (array $attributes): array {
            $scheduledAt = Carbon::now('UTC')->addHours(2);
            $datePart = $scheduledAt
                ->copy()
                ->setTimezone(config('delivery.receipt_date_timezone', 'Asia/Jakarta'))
                ->format('Ymd');

            $suffix = strtoupper(Str::random(4));

            return [
                'status' => DeliveryStatus::Scheduled->value,
                'scheduled_at' => $scheduledAt,
                'scheduled_at_recorded' => Carbon::now('UTC'),
                'scheduled_by_user_id' => User::factory(),
                'receipt_number' => sprintf('DEL-%s-%s', $datePart, $suffix),
                'kitchen_code' => 'K-'.$this->faker->unique()->numerify('####'),
                'kitchen_name' => $this->faker->company().' Kitchen',
                'kitchen_address' => $this->faker->streetAddress(),
                'kitchen_latitude' => $this->faker->randomFloat(7, -7.5, -6.0),
                'kitchen_longitude' => $this->faker->randomFloat(7, 106.0, 107.5),
                'customer_name' => $this->faker->name(),
                'customer_phone' => '+62'.$this->faker->numerify('##########'),
                'customer_address' => $this->faker->streetAddress(),
                'customer_latitude' => $this->faker->randomFloat(7, -7.5, -6.0),
                'customer_longitude' => $this->faker->randomFloat(7, 106.0, 107.5),
            ];
        });
    }

    /**
     * State: a delivery cancelled directly from `draft`. No receipt or
     * snapshot fields; only cancellation audit.
     */
    public function cancelledFromDraft(): static
    {
        return $this->state(fn (): array => [
            'status' => DeliveryStatus::Cancelled->value,
            'cancellation_reason' => 'Customer changed their mind.',
            'cancelled_at' => Carbon::now('UTC'),
            'cancelled_by_user_id' => User::factory(),
        ]);
    }

    /**
     * State: a delivery cancelled from `scheduled`. Receipt and snapshots
     * are preserved (AR-26).
     */
    public function cancelledFromScheduled(): static
    {
        return $this->scheduled()->state(fn (): array => [
            'status' => DeliveryStatus::Cancelled->value,
            'cancellation_reason' => 'Kitchen closed unexpectedly.',
            'cancelled_at' => Carbon::now('UTC'),
            'cancelled_by_user_id' => User::factory(),
        ]);
    }
}
