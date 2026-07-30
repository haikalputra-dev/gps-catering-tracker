@php
    /** @var \App\Models\Delivery $delivery */
    use App\Domain\Delivery\DeliveryStatus;
    use App\Domain\Identity\UserRole;

    $actor = auth()->user();
    $isOwner = $actor && $actor->role === UserRole::Owner;
    $isStaff = $actor && $actor->role === UserRole::Staff;
    $isCourier = $actor && $actor->role === UserRole::Courier;
    $isOfficeUser = $isOwner || $isStaff;
    $isAssignedCourier = $isCourier
        && $delivery->courier_id !== null
        && (int) $delivery->courier_id === (int) $actor->getKey();
@endphp

<h2 style="margin:0 0 12px;font-size:1.1rem;">Actions</h2>

{{-- Draft-only actions: only owner/staff may edit or schedule a draft. --}}
@if($isOfficeUser && $delivery->status === DeliveryStatus::Draft)
    <a class="btn" href="{{ route('deliveries.edit', $delivery) }}">Edit draft</a>
    <form method="POST" action="{{ route('deliveries.schedule', $delivery) }}" class="inline" style="margin-left:8px;">
        @csrf
        <button type="submit"
                onclick="return confirm('Schedule this delivery? A receipt number will be generated and kitchen/customer snapshots will be captured.');">
            Schedule
        </button>
    </form>
@endif

{{-- Dispatch: assigned courier may start a scheduled delivery (AR-41). --}}
@if($isAssignedCourier && $delivery->status === DeliveryStatus::Scheduled)
    <form method="POST" action="{{ route('deliveries.dispatch', $delivery) }}" class="inline">
        @csrf
        <button type="submit"
                onclick="return confirm('Start this delivery? You will be marked as in transit.');">
            Start Delivery
        </button>
    </form>
@endif

{{-- Mark delivered: assigned courier taps this when the food is handed over (AR-35). --}}
@if($isAssignedCourier && $delivery->status === DeliveryStatus::InTransit)
    <form method="POST" action="{{ route('deliveries.mark-delivered', $delivery) }}" class="inline">
        @csrf
        <button type="submit"
                onclick="return confirm('Mark this delivery as delivered? This action cannot be undone.');">
            Mark Delivered
        </button>
    </form>
@endif

{{--
    Cancel form: owner/staff may cancel from draft, scheduled, or in_transit.
    The assigned courier may only cancel from in_transit (AR-38 revised).
--}}
@php
    $canCancelAsOfficeUser = $isOfficeUser && in_array($delivery->status, [
        DeliveryStatus::Draft,
        DeliveryStatus::Scheduled,
        DeliveryStatus::InTransit,
    ], true);
    $canCancelAsCourier = $isAssignedCourier
        && $delivery->status === DeliveryStatus::InTransit;
    $showCancelForm = $canCancelAsOfficeUser || $canCancelAsCourier;
@endphp

@if($showCancelForm)
    <form method="POST" action="{{ route('deliveries.cancel', $delivery) }}" style="margin-top:16px;">
        @csrf
        <label for="cancellation_reason">Cancellation reason (3-255 chars)</label>
        <input
            type="text"
            id="cancellation_reason"
            name="cancellation_reason"
            minlength="3"
            maxlength="255"
            required
            value="{{ old('cancellation_reason') }}"
        />
        <div style="margin-top:8px;">
            <button type="submit" class="secondary"
                    onclick="return confirm('Cancel this delivery? This action cannot be undone.');">
                Cancel delivery
            </button>
        </div>
    </form>
@endif

@if($delivery->status === DeliveryStatus::Delivered || $delivery->status === DeliveryStatus::Cancelled)
    <p style="margin:0;color:#6b7280;">
        This delivery is in a terminal state. No further transitions are allowed.
    </p>
@endif
