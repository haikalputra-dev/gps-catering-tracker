<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Delivery;

use App\Domain\Delivery\DeliveryCanceller;
use App\Domain\Delivery\DeliveryStatus;
use App\Domain\Delivery\Exceptions\CancellationReasonRequiredException;
use App\Domain\Delivery\Exceptions\NotAuthorizedToCancelException;
use App\Domain\Delivery\Exceptions\NotCancellableStateException;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Kitchen;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryCancellerTest extends TestCase
{
    use RefreshDatabase;

    private function makeDraft(User $creator): Delivery
    {
        return Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->create(['created_by_user_id' => $creator->id]);
    }

    private function makeScheduled(User $creator): Delivery
    {
        return Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create(['created_by_user_id' => $creator->id]);
    }

    public function test_cancels_draft_and_records_audit(): void
    {
        $actor = User::factory()->owner()->create();
        $delivery = $this->makeDraft($actor);

        $cancelled = (new DeliveryCanceller())->cancel($delivery, $actor, '  Customer no-show  ');

        $this->assertSame(DeliveryStatus::Cancelled, $cancelled->status);
        $this->assertSame('Customer no-show', $cancelled->cancellation_reason);
        $this->assertSame($actor->id, $cancelled->cancelled_by_user_id);
        $this->assertNotNull($cancelled->cancelled_at);
        // Drafts had no receipt/snapshots, so those remain null.
        $this->assertNull($cancelled->receipt_number);
        $this->assertNull($cancelled->kitchen_code);
    }

    public function test_cancels_scheduled_and_preserves_receipt_and_snapshots(): void
    {
        $actor = User::factory()->owner()->create();
        $delivery = $this->makeScheduled($actor);

        $originalReceipt = $delivery->receipt_number;
        $originalKitchenCode = $delivery->kitchen_code;
        $originalCustomerName = $delivery->customer_name;
        $originalDistance = $delivery->distance_km;
        $originalFee = $delivery->fee_rupiah;

        $cancelled = (new DeliveryCanceller())->cancel($delivery, $actor, 'Kitchen closed');

        $this->assertSame(DeliveryStatus::Cancelled, $cancelled->status);
        $this->assertSame($originalReceipt, $cancelled->receipt_number);
        $this->assertSame($originalKitchenCode, $cancelled->kitchen_code);
        $this->assertSame($originalCustomerName, $cancelled->customer_name);
        $this->assertSame($originalDistance, $cancelled->distance_km);
        $this->assertSame($originalFee, $cancelled->fee_rupiah);
    }

    public function test_rejects_empty_reason(): void
    {
        $actor = User::factory()->owner()->create();
        $delivery = $this->makeDraft($actor);

        $this->expectException(CancellationReasonRequiredException::class);
        (new DeliveryCanceller())->cancel($delivery, $actor, '   ');
    }

    public function test_rejects_reason_too_short(): void
    {
        $actor = User::factory()->owner()->create();
        $delivery = $this->makeDraft($actor);

        $this->expectException(CancellationReasonRequiredException::class);
        (new DeliveryCanceller())->cancel($delivery, $actor, 'no');
    }

    public function test_rejects_reason_too_long(): void
    {
        $actor = User::factory()->owner()->create();
        $delivery = $this->makeDraft($actor);

        $this->expectException(CancellationReasonRequiredException::class);
        (new DeliveryCanceller())->cancel($delivery, $actor, str_repeat('a', 256));
    }

    public function test_rejects_already_cancelled_delivery(): void
    {
        $actor = User::factory()->owner()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->cancelledFromDraft()
            ->create(['created_by_user_id' => $actor->id]);

        $this->expectException(NotCancellableStateException::class);
        (new DeliveryCanceller())->cancel($delivery, $actor, 'trying again');
    }

    // -------------------------------------------------------------------
    // Packet 09: mid-route cancellation (AR-38 revised).
    //
    // The state machine now permits `in_transit -> cancelled`, but the
    // canceller enforces role- and ownership-aware actor rules:
    //   - Owner / staff may cancel any non-terminal delivery.
    //   - A courier may cancel ONLY their own in_transit delivery.
    //   - Any other actor combination is rejected with a domain
    //     exception (NotAuthorizedToCancelException).
    // -------------------------------------------------------------------

    public function test_owner_can_cancel_in_transit_delivery(): void
    {
        // Office role can intervene mid-route (e.g. customer cancels,
        // kitchen fire, courier ill). This preserves prior invariants
        // (receipt, snapshots, distance/fee frozen) and records the
        // cancelling actor.
        $owner = User::factory()->owner()->create();
        $courier = User::factory()->courier()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->inTransit()
            ->create([
                'created_by_user_id' => $owner->id,
                'courier_id' => $courier->id,
            ]);

        $result = (new DeliveryCanceller())
            ->cancel($delivery, $owner, 'customer cancelled mid-route');

        $this->assertSame(DeliveryStatus::Cancelled, $result->status);
        $this->assertSame($owner->id, $result->cancelled_by_user_id);
        $this->assertSame('customer cancelled mid-route', $result->cancellation_reason);
        // The dispatcher-stamped `dispatched_at` and the assigned
        // courier are preserved for audit.
        $this->assertNotNull($result->dispatched_at);
        $this->assertSame($courier->id, $result->courier_id);
    }

    public function test_staff_can_cancel_in_transit_delivery(): void
    {
        // Staff has the same cancellation authority as owner.
        $staff = User::factory()->staff()->create();
        $courier = User::factory()->courier()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->inTransit()
            ->create([
                'created_by_user_id' => $staff->id,
                'courier_id' => $courier->id,
            ]);

        $result = (new DeliveryCanceller())
            ->cancel($delivery, $staff, 'kitchen operational issue');

        $this->assertSame(DeliveryStatus::Cancelled, $result->status);
        $this->assertSame($staff->id, $result->cancelled_by_user_id);
    }

    public function test_assigned_courier_can_cancel_own_in_transit(): void
    {
        // AR-38 revised: the assigned courier may cancel their own
        // in-transit delivery. Reason is captured verbatim.
        $courier = User::factory()->courier()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->inTransit()
            ->create(['courier_id' => $courier->id]);

        $result = (new DeliveryCanceller())
            ->cancel($delivery, $courier, 'road blocked; customer notified');

        $this->assertSame(DeliveryStatus::Cancelled, $result->status);
        $this->assertSame($courier->id, $result->cancelled_by_user_id);
        $this->assertSame(
            'road blocked; customer notified',
            $result->cancellation_reason,
        );
    }

    public function test_non_assigned_courier_cannot_cancel_in_transit(): void
    {
        // Cross-courier interference is explicitly forbidden. The
        // service raises NotAuthorizedToCancelException BEFORE any
        // state change so the delivery row stays intact.
        $assignee = User::factory()->courier()->create();
        $intruder = User::factory()->courier()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->inTransit()
            ->create(['courier_id' => $assignee->id]);

        $this->expectException(NotAuthorizedToCancelException::class);
        try {
            (new DeliveryCanceller())
                ->cancel($delivery, $intruder, 'not mine');
        } finally {
            $delivery->refresh();
            $this->assertSame(DeliveryStatus::InTransit, $delivery->status);
            $this->assertNull($delivery->cancelled_by_user_id);
            $this->assertNull($delivery->cancellation_reason);
        }
    }

    public function test_courier_cannot_cancel_scheduled_delivery(): void
    {
        // A courier's cancellation window is strictly `in_transit`.
        // Cancelling a `scheduled` delivery is an office decision.
        $courier = User::factory()->courier()->create();
        $delivery = Delivery::factory()
            ->for(Kitchen::factory(), 'kitchen')
            ->for(Customer::factory(), 'customer')
            ->scheduled()
            ->create(['courier_id' => $courier->id]);

        $this->expectException(NotAuthorizedToCancelException::class);
        (new DeliveryCanceller())->cancel($delivery, $courier, 'too early');

        $this->assertSame(DeliveryStatus::Scheduled, $delivery->fresh()->status);
    }
}
