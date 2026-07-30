@php
    /** @var \App\Models\Delivery $delivery */
    use App\Domain\Delivery\DeliveryStatus;
@endphp

<h2 style="margin:0 0 12px;font-size:1.1rem;">Actions</h2>

@if($delivery->status === DeliveryStatus::Draft)
    <a class="btn" href="{{ route('deliveries.edit', $delivery) }}">Edit draft</a>
    <form method="POST" action="{{ route('deliveries.schedule', $delivery) }}" class="inline" style="margin-left:8px;">
        @csrf
        <button type="submit"
                onclick="return confirm('Schedule this delivery? A receipt number will be generated and kitchen/customer snapshots will be captured.');">
            Schedule
        </button>
    </form>
@endif

@if($delivery->status === DeliveryStatus::Draft || $delivery->status === DeliveryStatus::Scheduled)
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
