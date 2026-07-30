<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Delivery;

use App\Domain\Delivery\DeliveryDispatcher;
use App\Domain\Delivery\DeliveryStatus;
use App\Domain\Delivery\Exceptions\InactiveCourierException;
use App\Domain\Delivery\Exceptions\MissingCourierException;
use App\Domain\Delivery\Exceptions\NotAssignedCourierException;
use App\Domain\Delivery\Exceptions\NotDispatchableStateException;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Kitchen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Packet 09 — DeliveryDispatcher unit tests.
 *
 * The dispatcher owns the `scheduled -> in_transit` transition and
 * has three preconditions:
 *   - source state must be `scheduled` (state machine)
 *   - the delivery must have an assigned courier (defensive — should
 *     always be true if the scheduler ran, but a corrupt row must not
 *     silently succeed)
 *   - the acting user must be that assigned courier AND still active
 *
 * These tests exercise the domain service directly (unit-level) so we
 * can assert exception types and post-condition timestamps without
 * routing/middleware in the way.
 */
class DeliveryDispatcherTest extends TestCase
{
    use RefreshDatabase;

    private DeliveryDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = app(DeliveryDispatcher::class);
    }

    /**
     * Build a scheduled delivery assigned to $courier. Uses the
     * factory `scheduled()` state which already seeds a courier;
     * we override with the caller-supplied one for symmetry.
     */
    private function scheduledFor(User $courier): Delivery
    {
        return Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create([
                'courier_id' => $courier->id,
            ]);
    }

    public function test_dispatch_transitions_scheduled_to_in_transit(): void
    {
        $courier = User::factory()->courier()->create();
        $delivery = $this->scheduledFor($courier);

        // Read the raw stored value directly from the row rather
        // than going through Eloquent's casts. The dispatcher writes
        // `Carbon::now('UTC')` to a `datetime` column stored as a
        // naked string; Eloquent then re-hydrates it in the app
        // timezone (Asia/Jakarta), which shifts the perceived value
        // by the UTC offset. Comparing the raw string against
        // `Carbon::now('UTC')`'s formatted equivalent keeps the
        // assertion honest about *what the domain wrote*.
        $before = Carbon::now('UTC')->subSecond();
        $result = $this->dispatcher->dispatch($delivery, $courier);
        $after = Carbon::now('UTC')->addSecond();

        $this->assertSame(DeliveryStatus::InTransit, $result->status);

        $rawDispatchedAt = \Illuminate\Support\Facades\DB::table('deliveries')
            ->where('id', $result->getKey())
            ->value('dispatched_at');
        $this->assertNotNull($rawDispatchedAt);

        $storedUtc = Carbon::createFromFormat('Y-m-d H:i:s', $rawDispatchedAt, 'UTC');
        $this->assertNotFalse($storedUtc);
        $this->assertTrue(
            $storedUtc->between($before, $after),
            'dispatched_at should be stamped at UTC now within the call window.',
        );
    }

    public function test_dispatch_leaves_snapshot_and_pricing_untouched(): void
    {
        // The dispatcher is a pure state-transition service — it must
        // not touch kitchen/customer snapshots, distance, fee, or the
        // receipt number. Any accidental write would erode the audit
        // trail that upstream steps built up.
        $courier = User::factory()->courier()->create();
        $delivery = $this->scheduledFor($courier);

        $snapshot = [
            'receipt_number' => $delivery->receipt_number,
            'kitchen_code' => $delivery->kitchen_code,
            'kitchen_name' => $delivery->kitchen_name,
            'customer_name' => $delivery->customer_name,
            'distance_km' => $delivery->distance_km,
            'fee_rupiah' => $delivery->fee_rupiah,
        ];

        $result = $this->dispatcher->dispatch($delivery, $courier);

        foreach ($snapshot as $key => $value) {
            $this->assertSame(
                $value,
                $result->{$key},
                "Dispatcher must not modify {$key}.",
            );
        }
    }

    public function test_dispatch_rejects_draft_delivery(): void
    {
        // State-machine guard: only `scheduled` can be dispatched.
        // A `draft` has not been priced, has no receipt, and may not
        // even have a courier yet.
        $courier = User::factory()->courier()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->create([
                'created_by_user_id' => User::factory()->owner()->create()->id,
                'courier_id' => $courier->id,
            ]);

        $this->expectException(NotDispatchableStateException::class);
        $this->dispatcher->dispatch($delivery, $courier);

        // Sanity check the row was NOT mutated.
        $this->assertSame(DeliveryStatus::Draft, $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->dispatched_at);
    }

    public function test_dispatch_rejects_in_transit_delivery(): void
    {
        // Idempotence: dispatching an already-in-transit delivery
        // must not double-stamp `dispatched_at` or bounce the state.
        $courier = User::factory()->courier()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->inTransit()
            ->create(['courier_id' => $courier->id]);

        $originalDispatchedAt = $delivery->dispatched_at;

        $this->expectException(NotDispatchableStateException::class);
        try {
            $this->dispatcher->dispatch($delivery, $courier);
        } finally {
            $delivery->refresh();
            $this->assertSame(DeliveryStatus::InTransit, $delivery->status);
            $this->assertEquals($originalDispatchedAt, $delivery->dispatched_at);
        }
    }

    public function test_dispatch_rejects_terminal_delivery(): void
    {
        // `delivered` is terminal; dispatching it is nonsense.
        $courier = User::factory()->courier()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->delivered()
            ->create(['courier_id' => $courier->id]);

        $this->expectException(NotDispatchableStateException::class);
        $this->dispatcher->dispatch($delivery, $courier);
    }

    public function test_dispatch_rejects_non_assigned_courier(): void
    {
        // Actor identity: the courier attempting dispatch must be
        // the one assigned to the delivery. Cross-courier dispatch
        // is refused (AR-41: no reassignment).
        $assignee = User::factory()->courier()->create();
        $intruder = User::factory()->courier()->create();
        $delivery = $this->scheduledFor($assignee);

        $this->expectException(NotAssignedCourierException::class);
        $this->dispatcher->dispatch($delivery, $intruder);

        $this->assertSame(DeliveryStatus::Scheduled, $delivery->fresh()->status);
    }

    public function test_dispatch_rejects_inactive_courier_actor(): void
    {
        // Defensive check: middleware already blocks inactive users
        // from authenticating, but the domain service must not
        // depend on middleware for correctness.
        $courier = User::factory()->courier()->create();
        $delivery = $this->scheduledFor($courier);

        $courier->forceFill(['is_active' => false])->save();

        $this->expectException(InactiveCourierException::class);
        $this->dispatcher->dispatch($delivery, $courier);
    }

    public function test_dispatch_raises_when_courier_id_is_null(): void
    {
        // Corrupt-row defense. AR-37 says `courier_id` must be set at
        // scheduling; if a bad migration or manual DB edit removed
        // it, dispatch must not silently succeed.
        $courier = User::factory()->courier()->create();
        $delivery = $this->scheduledFor($courier);

        // Simulate the corruption without going through fillable.
        $delivery->forceFill(['courier_id' => null])->save();

        $this->expectException(MissingCourierException::class);
        $this->dispatcher->dispatch($delivery->refresh(), $courier);
    }
}
