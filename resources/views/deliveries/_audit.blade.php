@php
    /** @var \App\Models\Delivery $delivery */
    /** @var string $displayTz */
@endphp

<div class="card">
    <h2 style="margin:0 0 8px;font-size:1.1rem;">Audit trail</h2>
    <p style="margin:0;">
        <strong>Created:</strong>
        {{ $delivery->created_at?->copy()->setTimezone($displayTz)->format('Y-m-d H:i') }}
        by {{ $delivery->createdBy?->name ?? 'unknown' }}
    </p>
    @if($delivery->scheduled_at_recorded)
        <p style="margin:6px 0 0;">
            <strong>Scheduled:</strong>
            {{ $delivery->scheduled_at_recorded->copy()->setTimezone($displayTz)->format('Y-m-d H:i') }}
            by {{ $delivery->scheduledBy?->name ?? 'unknown' }}
        </p>
    @endif
    @if($delivery->cancelled_at)
        <p style="margin:6px 0 0;">
            <strong>Cancelled:</strong>
            {{ $delivery->cancelled_at->copy()->setTimezone($displayTz)->format('Y-m-d H:i') }}
            by {{ $delivery->cancelledBy?->name ?? 'unknown' }}
        </p>
        @if($delivery->cancellation_reason)
            <p style="margin:6px 0 0;">
                <strong>Reason:</strong>
                <span style="white-space:pre-wrap;">{{ $delivery->cancellation_reason }}</span>
            </p>
        @endif
    @endif
</div>
