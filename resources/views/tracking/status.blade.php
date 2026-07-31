@extends('layouts.public')

@section('title', 'Delivery status')

@php
    /** @var \App\Models\Delivery $delivery */
    use App\Domain\Delivery\DeliveryStatus;

    $status = $delivery->status;
    $statusValue = $status->value;
    $displayTz = 'Asia/Jakarta';

    $isScheduled = $statusValue === DeliveryStatus::Scheduled->value;
    $isInTransit = $statusValue === DeliveryStatus::InTransit->value;
    $isDelivered = $statusValue === DeliveryStatus::Delivered->value;
    $isCancelled = $statusValue === DeliveryStatus::Cancelled->value;

    $fmt = static fn ($ts) => $ts
        ? $ts->copy()->timezone($displayTz)->format('D, d M Y H:i')
        : null;

    $scheduledAt = $fmt($delivery->scheduled_at_recorded ?? $delivery->scheduled_at);
    $dispatchedAt = $fmt($delivery->dispatched_at);
    $deliveredAt = $fmt($delivery->delivered_at);
    $cancelledAt = $fmt($delivery->cancelled_at);

    $timestamps = [
        'scheduled_at_recorded' => $delivery->scheduled_at_recorded ?? $delivery->scheduled_at,
        'in_transit_at'         => $delivery->dispatched_at,
        'delivered_at'          => $delivery->delivered_at,
        'cancelled_at'          => $delivery->cancelled_at,
        'cancellation_reason'   => $delivery->cancellation_reason,
    ];

    // ETA: rough estimate at 30 km/h average speed => 0.5 km/min.
    // Only used as a hint when in transit and distance is known.
    $etaMinutes = null;
    if ($isInTransit && $delivery->distance_km !== null) {
        $etaMinutes = max(1, (int) ceil((float) $delivery->distance_km / 0.5));
    }
@endphp

@section('content')
    {{-- Hero card --}}
    <x-card>
        <div class="text-center">
            <p class="text-xs uppercase tracking-wide font-medium text-slate-500">Delivery status</p>
            <h1 class="mt-1 text-3xl font-bold text-slate-900">{{ $status->label() }}</h1>

            <div class="mt-4 flex justify-center">
                @include('deliveries._status_badge', ['status' => $delivery->status])
            </div>

            <div class="mt-6 pt-6 border-t border-slate-200 inline-flex flex-col items-center">
                <p class="text-xs uppercase tracking-wide font-medium text-slate-500">Receipt</p>
                <code class="mt-1 text-base bg-slate-100 rounded px-3 py-1 text-slate-900 font-mono font-semibold">{{ $delivery->receipt_number }}</code>
            </div>

            @if($etaMinutes !== null)
                <div class="mt-6 inline-flex items-center gap-2 px-4 py-2 bg-orange-50 border border-orange-200 rounded-lg">
                    <x-heroicon-o-clock class="w-5 h-5 text-orange-600" />
                    <p class="text-sm text-slate-900">
                        Arriving in approximately
                        <span class="font-bold text-orange-700">{{ $etaMinutes }} min</span>
                    </p>
                </div>
                <p class="mt-2 text-xs text-slate-500">Rough estimate based on straight-line distance.</p>
            @elseif($isDelivered && $deliveredAt)
                <p class="mt-6 text-sm font-medium text-emerald-700">
                    Delivered on {{ $deliveredAt }}
                </p>
            @elseif($isScheduled && $scheduledAt)
                <p class="mt-6 text-sm text-slate-600">
                    Scheduled for {{ $scheduledAt }}
                </p>
            @endif
        </div>
    </x-card>

    {{-- Trip (from → to) --}}
    <div>
        <h2 class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-3">Trip details</h2>
        <x-trip-card
            :fromName="$delivery->kitchen_name"
            :fromAddress="$delivery->kitchen_address"
            :toName="$delivery->customer_name"
            :toAddress="$delivery->customer_address"
            :distance="$delivery->distance_km"
        />
    </div>

    {{-- Distance and fee --}}
    <x-card>
        <h2 class="text-sm font-semibold text-slate-900 mb-3">Distance and fee</h2>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <dt class="text-xs uppercase tracking-wide font-medium text-slate-500">Distance</dt>
                <dd class="mt-1 text-lg font-semibold text-slate-900">
                    {{ number_format((float) $delivery->distance_km, 2, '.', ',') }} km
                </dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide font-medium text-slate-500">Delivery fee</dt>
                <dd class="mt-1 text-lg font-semibold text-slate-900">
                    Rp {{ number_format((int) $delivery->fee_rupiah, 0, ',', '.') }}
                </dd>
            </div>
        </div>
    </x-card>

    {{-- Courier contact (only when in transit) --}}
    @if($isInTransit && $delivery->courier)
        <x-card>
            <h2 class="text-sm font-semibold text-slate-900 mb-3 flex items-center gap-2">
                <x-heroicon-o-user class="w-4 h-4 text-slate-500" />
                Your courier
            </h2>
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-full bg-orange-100 flex items-center justify-center shrink-0 text-orange-700 font-semibold text-lg">
                    {{ mb_strtoupper(mb_substr($delivery->courier->name, 0, 1)) }}
                </div>
                <div class="min-w-0">
                    <p class="font-semibold text-slate-900">{{ $delivery->courier->name }}</p>
                    @if(!empty($delivery->courier->phone))
                        <a href="tel:{{ $delivery->courier->phone }}" class="inline-flex items-center gap-1.5 text-sm text-orange-700 hover:text-orange-800 font-medium mt-1">
                            <x-heroicon-o-phone class="w-4 h-4" />
                            {{ $delivery->courier->phone }}
                        </a>
                    @endif
                </div>
            </div>
            <p class="mt-3 text-xs text-slate-500">Your courier is on the way.</p>
        </x-card>
    @endif

    {{--
        Live-map (Packet 12, AR-55 / AR-56 / AR-57). Customer surface
        is gated on `in_transit` only; before dispatch there is no
        courier position to show, and after delivery/cancellation the
        tracking page transitions to a terminal state. The endpoint is
        session-scoped and returns 401 for any stale/expired session.

        The map <div> below is test-locked and consumed by
        resources/js/live-map.js. Do NOT change its id or data-* attributes.
    --}}
    @if($isInTransit)
        <x-card>
            <div class="flex items-center justify-between mb-2">
                <h2 class="text-sm font-semibold text-slate-900 flex items-center gap-2">
                    <x-heroicon-o-map class="w-4 h-4 text-slate-500" />
                    Live map
                </h2>
                <span class="text-xs text-slate-500">
                    Auto-refreshing every {{ (int) (config('telemetry.polling_interval_ms', 3000) / 1000) }}s
                </span>
            </div>
            <div
                id="tracking-live-map"
                class="live-map-container rounded-lg overflow-hidden border border-slate-200"
                style="height: 500px;"
                data-live-map
                data-endpoint="{{ route('tracking.telemetry.latest') }}"
                data-interval="{{ (int) config('telemetry.polling_interval_ms', 3000) }}"
                data-kitchen-latitude="{{ $delivery->kitchen_latitude }}"
                data-kitchen-longitude="{{ $delivery->kitchen_longitude }}"
                data-customer-latitude="{{ $delivery->customer_latitude }}"
                data-customer-longitude="{{ $delivery->customer_longitude }}"
                data-tile-url="{{ config('map.tile_url') }}"
                data-tile-attribution="{{ config('map.tile_attribution') }}"
                data-tile-max-zoom="{{ (int) config('map.tile_max_zoom', 19) }}"
                data-status-target="tracking-live-map-status"
            ></div>
            <p
                id="tracking-live-map-status"
                class="live-map-status mt-3 text-sm text-slate-600"
            >
                Waiting for the first live position.
            </p>
        </x-card>
    @endif

    {{-- Timeline / Progress --}}
    <x-card>
        <h2 class="text-sm font-semibold text-slate-900 mb-4">Progress</h2>
        <x-timeline
            :currentStatus="$delivery->status"
            :timestamps="$timestamps"
        />
    </x-card>

    {{-- Sign out --}}
    <x-card>
        <form method="POST" action="{{ route('tracking.signOut') }}">
            @csrf
            <x-button type="submit" variant="secondary" class="w-full justify-center">
                Look up another delivery
            </x-button>
        </form>
    </x-card>
@endsection
