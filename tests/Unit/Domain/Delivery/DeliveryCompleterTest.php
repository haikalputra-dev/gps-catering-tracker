<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Delivery;

use App\Domain\Delivery\DeliveryCompleter;
use App\Domain\Delivery\DeliveryStatus;
use App\Domain\Delivery\Exceptions\InactiveCourierException;
use App\Domain\Delivery\Exceptions\NotAssignedCourierException;
use App\Domain\Delivery\Exceptions\NotCompletableStateException;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Kitchen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Packet 09 — DeliveryCompleter unit tests.
 *
 * The completer owns the `in_transit -> delivered` transition. It has
 * three preconditions:
 *   - source state must be `in_transit`
 *   - the acting user must be the assigned courier
 *   - the acting user must still be active
 *
 * Post-condition:
 *   - `delivered_at` is stamped at UTC now
 *   - `delivered_at` is guaranteed to be >= `dispatched_at`, since
 *     the completer runs *after* the dispatcher in real time
 *
 * These tests exercise the domain service directly so we can assert
 * exception types and post-condition invariants without routing/
 * middleware in the way.
 */
class DeliveryCompleterTest extends TestCase
{
    use RefreshDatabase;

    private DeliveryCompleter $completer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->completer = app(DeliveryCompleter::class);
    }

    /**
     * Build an in-transit delivery assigned to $courier. Uses the
     * factory `inTransit()` state so `dispatched_at` is already set.
     */
    private function inTransitFor(User $courier): Delivery
    {
        return Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->inTransit()
            ->create(['courier_id' => $courier->id]);
    }

    public function test_complete_transitions_in_transit_to_delivered(): void
    {
        $courier = User::factory()->courier()->create();
        $delivery = $this->inTransitFor($courier);

        // Widened one-second window on each end to absorb sub-second
        // rounding at the storage layer.
        $before = Carbon::now('UTC')->subSecond();
        $result = $this->completer->complete($delivery, $courier);
        $after = Carbon::now('UTC')->addSecond();

        $this->assertSame(DeliveryStatus::Delivered, $result->status);

        // Read the raw stored value so we sidestep app-tz casting.
        $rawDeliveredAt = DB::table('deliveries')
            ->where('id', $result->getKey())
            ->value('delivered_at');
        $this->assertNotNull($rawDeliveredAt);
        $storedUtc = Carbon::createFromFormat('Y-m-d H:i:s', $rawDeliveredAt, 'UTC');
        $this->assertNotFalse($storedUtc);
        $this->assertTrue(
            $storedUtc->between($before, $after),
            'delivered_at should be stamped at UTC now within the call window.',
        );
    }

    public function test_delivered_at_is_not_before_dispatched_at(): void
    {
        // Real-world causality: a courier cannot deliver before
        // dispatching. The factory seeds `dispatched_at` in the past
        // (roughly now minus a few minutes) and the completer stamps
        // `delivered_at` at real UTC now, so the invariant should
        // hold by construction. This test protects that ordering.
        $courier = User::factory()->courier()->create();
        $delivery = $this->inTransitFor($courier);

        $result = $this->completer->complete($delivery, $courier);

        $rawDispatchedAt = DB::table('deliveries')
            ->where('id', $result->getKey())
            ->value('dispatched_at');
        $rawDeliveredAt = DB::table('deliveries')
            ->where('id', $result->getKey())
            ->value('delivered_at');

        $this->assertGreaterThanOrEqual(
            $rawDispatchedAt,
            $rawDeliveredAt,
            'delivered_at must be >= dispatched_at.',
        );
    }

    public function test_complete_leaves_snapshot_and_pricing_untouched(): void
    {
        // The completer is a pure state-transition service. It must
        // not modify kitchen/customer snapshots, distance, fee,
        // receipt, courier or dispatched_at.
        $courier = User::factory()->courier()->create();
        $delivery = $this->inTransitFor($courier);

        $snapshot = [
            'receipt_number' => $delivery->receipt_number,
            'kitchen_code' => $delivery->kitchen_code,
            'kitchen_name' => $delivery->kitchen_name,
            'customer_name' => $delivery->customer_name,
            'distance_km' => $delivery->distance_km,
            'fee_rupiah' => $delivery->fee_rupiah,
            'courier_id' => $delivery->courier_id,
        ];
        $originalDispatchedAtRaw = DB::table('deliveries')
            ->where('id', $delivery->getKey())
            ->value('dispatched_at');

        $result = $this->completer->complete($delivery, $courier);

        foreach ($snapshot as $key => $value) {
            $this->assertSame(
                $value,
                $result->{$key},
                "Completer must not modify {$key}.",
            );
        }

        $currentDispatchedAtRaw = DB::table('deliveries')
            ->where('id', $result->getKey())
            ->value('dispatched_at');
        $this->assertSame(
            $originalDispatchedAtRaw,
            $currentDispatchedAtRaw,
            'Completer must not modify dispatched_at.',
        );
    }

    public function test_complete_rejects_scheduled_delivery(): void
    {
        // State-machine guard: only `in_transit` can be delivered.
        // A `scheduled` delivery has not been dispatched yet.
        $courier = User::factory()->courier()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create(['courier_id' => $courier->id]);

        $this->expectException(NotCompletableStateException::class);
        try {
            $this->completer->complete($delivery, $courier);
        } finally {
            $delivery->refresh();
            $this->assertSame(DeliveryStatus::Scheduled, $delivery->status);
            $this->assertNull($delivery->delivered_at);
        }
    }

    public function test_complete_rejects_already_delivered_delivery(): void
    {
        // Idempotence: marking an already-delivered delivery as
        // delivered again must not double-stamp `delivered_at`.
        $courier = User::factory()->courier()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->delivered()
            ->create(['courier_id' => $courier->id]);

        $originalDeliveredAt = $delivery->delivered_at;

        $this->expectException(NotCompletableStateException::class);
        try {
            $this->completer->complete($delivery, $courier);
        } finally {
            $delivery->refresh();
            $this->assertSame(DeliveryStatus::Delivered, $delivery->status);
            $this->assertEquals($originalDeliveredAt, $delivery->delivered_at);
        }
    }

    public function test_complete_rejects_cancelled_delivery(): void
    {
        // A cancelled delivery is terminal in the other direction;
        // completing it would resurrect a dead delivery.
        $courier = User::factory()->courier()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->cancelledFromInTransit()
            ->create(['courier_id' => $courier->id]);

        $this->expectException(NotCompletableStateException::class);
        $this->completer->complete($delivery, $courier);
    }

    public function test_complete_rejects_non_assigned_courier(): void
    {
        // Actor identity: only the assigned courier may mark a
        // delivery as delivered. Cross-courier completion is refused.
        $assignee = User::factory()->courier()->create();
        $intruder = User::factory()->courier()->create();
        $delivery = $this->inTransitFor($assignee);

        $this->expectException(NotAssignedCourierException::class);
        $this->completer->complete($delivery, $intruder);

        $this->assertSame(DeliveryStatus::InTransit, $delivery->fresh()->status);
    }

    public function test_complete_rejects_inactive_courier_actor(): void
    {
        // Defensive check: middleware already blocks inactive users
        // from authenticating, but the domain service must not
        // depend on middleware for correctness.
        $courier = User::factory()->courier()->create();
        $delivery = $this->inTransitFor($courier);

        $courier->forceFill(['is_active' => false])->save();

        $this->expectException(InactiveCourierException::class);
        $this->completer->complete($delivery, $courier);
    }
}
