@php
    /** @var \App\Models\Delivery $delivery */
    use App\Domain\Delivery\DeliveryStatus;

    $displayTz = config('delivery.receipt_date_timezone', 'Asia/Jakarta');
@endphp
@extends('layouts.app')

@section('title', 'Delivery #' . $delivery->id)

@section('content')
    <x-page-header>
        <x-slot:title>
            <span class="inline-flex items-baseline gap-3 flex-wrap">
                <span>Delivery #{{ $delivery->id }}</span>
                @if($delivery->receipt_number)
                    <code class="text-sm bg-slate-100 rounded px-2 py-0.5 text-slate-700 font-mono">{{ $delivery->receipt_number }}</code>
                @endif
            </span>
        </x-slot:title>
        <x-slot:subtitle>
            @include('deliveries._status_badge', ['status' => $delivery->status])
        </x-slot:subtitle>
        <x-slot:actions>
            <x-button :href="route('deliveries.index')" variant="secondary">Back to list</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <x-card title="Kitchen">
            @if($delivery->status === DeliveryStatus::Draft)
                <p class="text-xs text-slate-500 italic mb-2">Live reference (snapshot captured at scheduling):</p>
                <p class="text-slate-900">
                    <strong>{{ $delivery->kitchen?->code }}</strong> — {{ $delivery->kitchen?->name }}
                </p>
                <p class="text-slate-600 text-sm mt-1">{{ $delivery->kitchen?->address }}</p>
            @else
                <p class="text-slate-900">
                    <strong>{{ $delivery->kitchen_code }}</strong> — {{ $delivery->kitchen_name }}
                </p>
                <p class="text-slate-600 text-sm mt-1">{{ $delivery->kitchen_address }}</p>
                <p class="text-slate-500 text-xs mt-2">
                    Lat/Lng: {{ $delivery->kitchen_latitude }}, {{ $delivery->kitchen_longitude }}
                </p>
            @endif
        </x-card>

        <x-card title="Customer">
            @if($delivery->status === DeliveryStatus::Draft)
                <p class="text-xs text-slate-500 italic mb-2">Live reference (snapshot captured at scheduling):</p>
                <p class="text-slate-900">
                    <strong>{{ $delivery->customer?->name }}</strong>
                    <span class="text-slate-600">({{ $delivery->customer?->phone }})</span>
                </p>
                <p class="text-slate-600 text-sm mt-1">{{ $delivery->customer?->address }}</p>
            @else
                <p class="text-slate-900">
                    <strong>{{ $delivery->customer_name }}</strong>
                    <span class="text-slate-600">({{ $delivery->customer_phone }})</span>
                </p>
                <p class="text-slate-600 text-sm mt-1">{{ $delivery->customer_address }}</p>
                <p class="text-slate-500 text-xs mt-2">
                    Lat/Lng: {{ $delivery->customer_latitude }}, {{ $delivery->customer_longitude }}
                </p>
            @endif
        </x-card>

        <x-card title="Courier">
            @if($delivery->courier)
                <p class="text-slate-900">
                    <strong>{{ $delivery->courier->name }}</strong>
                    <span class="text-slate-500 text-sm">(#{{ $delivery->courier->id }})</span>
                </p>
            @else
                <p class="text-slate-500 italic text-sm">No courier assigned yet.</p>
            @endif
            <dl class="mt-3 space-y-1.5 text-sm">
                <div class="flex gap-2">
                    <dt class="font-medium text-slate-700 w-32">Dispatched at:</dt>
                    <dd class="text-slate-900">
                        @if($delivery->dispatched_at)
                            {{ $delivery->dispatched_at->copy()->setTimezone($displayTz)->format('Y-m-d H:i') }}
                            <span class="text-slate-500 text-xs">({{ $displayTz }})</span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </dd>
                </div>
                <div class="flex gap-2">
                    <dt class="font-medium text-slate-700 w-32">Delivered at:</dt>
                    <dd class="text-slate-900">
                        @if($delivery->delivered_at)
                            {{ $delivery->delivered_at->copy()->setTimezone($displayTz)->format('Y-m-d H:i') }}
                            <span class="text-slate-500 text-xs">({{ $displayTz }})</span>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </dd>
                </div>
            </dl>
        </x-card>

        <x-card title="Schedule">
            <dl class="space-y-2 text-sm">
                <div>
                    <dt class="font-medium text-slate-700">Scheduled for:</dt>
                    <dd class="text-slate-900 mt-0.5">
                        @if($delivery->scheduled_at)
                            {{ $delivery->scheduled_at->copy()->setTimezone($displayTz)->format('Y-m-d H:i') }}
                            <span class="text-slate-500 text-xs">({{ $displayTz }})</span>
                        @else
                            <span class="text-slate-400">Not set</span>
                        @endif
                    </dd>
                </div>
                @if($delivery->notes)
                    <div>
                        <dt class="font-medium text-slate-700">Notes:</dt>
                        <dd class="text-slate-700 mt-0.5 whitespace-pre-wrap">{{ $delivery->notes }}</dd>
                    </div>
                @endif
            </dl>
        </x-card>
    </div>

    {{--
        Pricing block is hidden from courier viewers (AR-40). Rendered as
        DOM-absent (not CSS-hidden) so the fee never reaches the browser
        for a courier session.
    --}}
    @if(!auth()->user()?->isCourier())
        <x-card title="Pricing">
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div class="flex items-baseline gap-2">
                    <dt class="font-medium text-slate-700">Distance:</dt>
                    <dd class="text-lg font-semibold text-slate-900">
                        @if($delivery->distance_km !== null)
                            {{ number_format((float) $delivery->distance_km, 3, '.', '') }} km
                        @else
                            <span class="text-slate-400 text-base font-normal">—</span>
                        @endif
                    </dd>
                </div>
                <div class="flex items-baseline gap-2">
                    <dt class="font-medium text-slate-700">Fee:</dt>
                    <dd class="text-lg font-semibold text-slate-900">
                        @if($delivery->fee_rupiah !== null)
                            Rp {{ number_format((int) $delivery->fee_rupiah, 0, ',', '.') }}
                        @else
                            <span class="text-slate-400 text-base font-normal">—</span>
                        @endif
                    </dd>
                </div>
            </dl>
            <p class="mt-3 text-xs text-slate-500">
                Fee is calculated once at scheduling using the straight-line distance
                between the kitchen and the customer. It is not recalculated afterwards.
            </p>
        </x-card>
    @endif

    {{--
        Live-map block (Packet 12, AR-55 / AR-56 / AR-57). Rendered
        for `scheduled` and `in_transit` deliveries; before scheduling
        no snapshot coordinates exist, and after delivery/cancellation
        there is no live position to show. The map polls
        `deliveries.telemetry.latest` at the interval declared by
        `config('telemetry.polling_interval_ms')`.
    --}}
    @php
        $liveStatuses = [DeliveryStatus::Scheduled, DeliveryStatus::InTransit];
    @endphp
    @if(in_array($delivery->status, $liveStatuses, true))
        <x-card title="Live position">
            <p class="text-sm text-slate-500 mb-3">
                @if($delivery->status === DeliveryStatus::Scheduled)
                    Awaiting dispatch — the courier marker appears once the delivery starts.
                @else
                    Auto-refreshing every {{ (int) (config('telemetry.polling_interval_ms', 3000) / 1000) }}s.
                @endif
            </p>
            <div
                id="delivery-live-map"
                class="live-map-container rounded-lg overflow-hidden border border-slate-200"
                data-live-map
                data-endpoint="{{ route('deliveries.telemetry.latest', $delivery) }}"
                data-interval="{{ (int) config('telemetry.polling_interval_ms', 3000) }}"
                data-kitchen-latitude="{{ $delivery->kitchen_latitude }}"
                data-kitchen-longitude="{{ $delivery->kitchen_longitude }}"
                data-customer-latitude="{{ $delivery->customer_latitude }}"
                data-customer-longitude="{{ $delivery->customer_longitude }}"
                data-tile-url="{{ config('map.tile_url') }}"
                data-tile-attribution="{{ config('map.tile_attribution') }}"
                data-tile-max-zoom="{{ (int) config('map.tile_max_zoom', 19) }}"
                data-status-target="delivery-live-map-status"
            ></div>
            <p
                id="delivery-live-map-status"
                class="live-map-status mt-3 text-sm text-slate-600"
            >
                @if($delivery->status === DeliveryStatus::Scheduled)
                    Awaiting first live position.
                @else
                    Waiting for the first live position.
                @endif
            </p>
        </x-card>
    @endif

    @include('deliveries._audit', ['delivery' => $delivery, 'displayTz' => $displayTz])

    <x-card title="Actions">
        @include('deliveries._action_buttons', ['delivery' => $delivery])
    </x-card>
@endsection
