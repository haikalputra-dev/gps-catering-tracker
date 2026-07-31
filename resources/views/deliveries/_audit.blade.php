@php
    /** @var \App\Models\Delivery $delivery */
    /** @var string $displayTz */
@endphp

<x-card title="Audit trail">
    <dl class="space-y-3 text-sm">
        <div class="flex flex-wrap gap-2">
            <dt class="font-medium text-slate-700 w-32">Created:</dt>
            <dd class="text-slate-900">
                {{ $delivery->created_at?->copy()->setTimezone($displayTz)->format('Y-m-d H:i') }}
                <span class="text-slate-500">by {{ $delivery->createdBy?->name ?? 'unknown' }}</span>
            </dd>
        </div>
        @if($delivery->scheduled_at_recorded)
            <div class="flex flex-wrap gap-2">
                <dt class="font-medium text-slate-700 w-32">Scheduled:</dt>
                <dd class="text-slate-900">
                    {{ $delivery->scheduled_at_recorded->copy()->setTimezone($displayTz)->format('Y-m-d H:i') }}
                    <span class="text-slate-500">by {{ $delivery->scheduledBy?->name ?? 'unknown' }}</span>
                </dd>
            </div>
        @endif
        @if($delivery->cancelled_at)
            <div class="flex flex-wrap gap-2">
                <dt class="font-medium text-slate-700 w-32">Cancelled:</dt>
                <dd class="text-slate-900">
                    {{ $delivery->cancelled_at->copy()->setTimezone($displayTz)->format('Y-m-d H:i') }}
                    <span class="text-slate-500">by {{ $delivery->cancelledBy?->name ?? 'unknown' }}</span>
                </dd>
            </div>
            @if($delivery->cancellation_reason)
                <div>
                    <dt class="font-medium text-slate-700 mb-1">Reason:</dt>
                    <dd class="text-slate-700 whitespace-pre-wrap">{{ $delivery->cancellation_reason }}</dd>
                </div>
            @endif
        @endif
    </dl>
</x-card>
