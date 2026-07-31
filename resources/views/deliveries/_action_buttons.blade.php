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

<h2 class="text-lg font-semibold text-slate-900 mb-4">Actions</h2>

<div class="space-y-4">
    {{-- Draft-only actions: only owner/staff may edit or schedule a draft. --}}
    @if($isOfficeUser && $delivery->status === DeliveryStatus::Draft)
        <div class="flex flex-wrap items-center gap-3">
            <x-button :href="route('deliveries.edit', $delivery)">Edit draft</x-button>
            <form method="POST" action="{{ route('deliveries.schedule', $delivery) }}" class="inline">
                @csrf
                <x-button type="submit"
                          onclick="return confirm('Schedule this delivery? A receipt number will be generated and kitchen/customer snapshots will be captured.');">
                    Schedule
                </x-button>
            </form>
        </div>
    @endif

    {{-- Dispatch: assigned courier may start a scheduled delivery (AR-41). --}}
    @if($isAssignedCourier && $delivery->status === DeliveryStatus::Scheduled)
        <form method="POST" action="{{ route('deliveries.dispatch', $delivery) }}">
            @csrf
            <x-button type="submit"
                      onclick="return confirm('Start this delivery? You will be marked as in transit.');">
                Start Delivery
            </x-button>
        </form>
    @endif

    {{-- Mark delivered: assigned courier taps this when the food is handed over (AR-35). --}}
    @if($isAssignedCourier && $delivery->status === DeliveryStatus::InTransit)
        <form method="POST" action="{{ route('deliveries.mark-delivered', $delivery) }}">
            @csrf
            <x-button type="submit"
                      variant="success"
                      onclick="return confirm('Mark this delivery as delivered? This action cannot be undone.');">
                Mark Delivered
            </x-button>
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
        <form method="POST" action="{{ route('deliveries.cancel', $delivery) }}"
              class="mt-2 border-t border-slate-200 pt-4 space-y-3">
            @csrf
            <x-form-field
                name="cancellation_reason"
                label="Cancellation reason (3-255 chars)"
                :value="old('cancellation_reason')"
                :required="true"
                minlength="3"
                maxlength="255" />
            <x-button type="submit"
                      variant="danger"
                      onclick="return confirm('Cancel this delivery? This action cannot be undone.');">
                Cancel delivery
            </x-button>
        </form>
    @endif

    @if($delivery->status === DeliveryStatus::Delivered || $delivery->status === DeliveryStatus::Cancelled)
        <p class="text-sm text-slate-500">
            This delivery is in a terminal state. No further transitions are allowed.
        </p>
    @endif
</div>
