@extends('layouts.public')

@section('title', 'Delivery status')

@php
    /** @var \App\Models\Delivery $delivery */
    $status = $delivery->status;
    $statusValue = $status->value;
    $tz = 'Asia/Jakarta';
    $fmt = static fn ($ts) => $ts ? $ts->copy()->timezone('Asia/Jakarta')->format('D, d M Y H:i') : null;

    $scheduledAt = $fmt($delivery->scheduled_at_recorded ?? $delivery->scheduled_at);
    $dispatchedAt = $fmt($delivery->dispatched_at);
    $deliveredAt = $fmt($delivery->delivered_at);
    $cancelledAt = $fmt($delivery->cancelled_at);

    $isInTransit = $statusValue === \App\Domain\Delivery\DeliveryStatus::InTransit->value;
    $isDelivered = $statusValue === \App\Domain\Delivery\DeliveryStatus::Delivered->value;
    $isCancelled = $statusValue === \App\Domain\Delivery\DeliveryStatus::Cancelled->value;

    // Timeline step palette (semantic).
    $stepDone = 'bg-emerald-500 text-white ring-emerald-100';
    $stepActive = 'bg-orange-500 text-white ring-orange-100';
    $stepPending = 'bg-slate-200 text-slate-500 ring-slate-100';
    $stepCancelled = 'bg-red-500 text-white ring-red-100';
@endphp

@section('content')
    <x-card>
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
                <p class="text-xs uppercase tracking-wide font-medium text-slate-500">Delivery status</p>
                <h1 class="mt-1 text-2xl font-bold text-slate-900">{{ $status->label() }}</h1>
            </div>
            @include('deliveries._status_badge', ['status' => $delivery->status])
        </div>
        <dl class="mt-4 pt-4 border-t border-slate-200">
            <div class="flex items-baseline gap-2">
                <dt class="text-sm font-medium text-slate-500">Receipt</dt>
                <dd><code class="text-sm bg-slate-100 rounded px-2 py-0.5 text-slate-800 font-mono">{{ $delivery->receipt_number }}</code></dd>
            </div>
        </dl>
    </x-card>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <x-card>
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-full bg-orange-100 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-orange-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44l-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-semibold text-slate-900">From</h2>
                    <p class="mt-1 font-medium text-slate-900">{{ $delivery->kitchen_name }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $delivery->kitchen_address }}</p>
                </div>
            </div>
        </x-card>

        <x-card>
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-emerald-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>
                    </svg>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-semibold text-slate-900">To</h2>
                    <p class="mt-1 font-medium text-slate-900">{{ $delivery->customer_name }}</p>
                    <p class="mt-1 text-sm text-slate-600">{{ $delivery->customer_address }}</p>
                </div>
            </div>
        </x-card>
    </div>

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

    @if($isInTransit && $delivery->courier)
        <x-card>
            <h2 class="text-sm font-semibold text-slate-900 mb-3">Courier</h2>
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-sky-100 flex items-center justify-center shrink-0 text-sky-700 font-medium">
                    {{ mb_strtoupper(mb_substr($delivery->courier->name, 0, 1)) }}
                </div>
                <div>
                    <p class="font-medium text-slate-900">{{ $delivery->courier->name }}</p>
                    @if(!empty($delivery->courier->phone))
                        <p class="text-sm text-slate-600">{{ $delivery->courier->phone }}</p>
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
    --}}
    @if($isInTransit)
        <x-card>
            <h2 class="text-sm font-semibold text-slate-900 mb-2">Live map</h2>
            <p class="text-xs text-slate-500 mb-3">
                Auto-refreshing every {{ (int) (config('telemetry.polling_interval_ms', 3000) / 1000) }}s.
            </p>
            <div
                id="tracking-live-map"
                class="live-map-container rounded-lg overflow-hidden border border-slate-200"
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

    <x-card>
        <h2 class="text-sm font-semibold text-slate-900 mb-4">Timeline</h2>
        <ol class="relative border-l-2 border-slate-200 ml-3 space-y-6">
            {{-- Scheduled --}}
            <li class="ml-6">
                <span class="absolute -left-3 flex items-center justify-center w-6 h-6 rounded-full ring-4 {{ $scheduledAt ? $stepDone : $stepPending }}">
                    @if($scheduledAt)
                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                        </svg>
                    @endif
                </span>
                <div>
                    <p class="text-sm font-semibold text-slate-900">Scheduled</p>
                    @if($scheduledAt)
                        <p class="text-xs text-slate-600 mt-0.5">{{ $scheduledAt }}</p>
                    @else
                        <p class="text-xs text-slate-400 mt-0.5 italic">Pending</p>
                    @endif
                </div>
            </li>

            {{-- Dispatched --}}
            <li class="ml-6">
                <span class="absolute -left-3 flex items-center justify-center w-6 h-6 rounded-full ring-4 {{ $dispatchedAt ? $stepDone : ($isCancelled ? $stepPending : ($isInTransit ? $stepActive : $stepPending)) }}">
                    @if($dispatchedAt)
                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                        </svg>
                    @endif
                </span>
                <div>
                    <p class="text-sm font-semibold text-slate-900">Dispatched</p>
                    @if($dispatchedAt)
                        <p class="text-xs text-slate-600 mt-0.5">{{ $dispatchedAt }}</p>
                    @elseif($isCancelled && !$dispatchedAt)
                        <p class="text-xs text-slate-400 mt-0.5 italic">Not dispatched</p>
                    @else
                        <p class="text-xs text-slate-400 mt-0.5 italic">Pending</p>
                    @endif
                </div>
            </li>

            {{-- Delivered --}}
            <li class="ml-6">
                <span class="absolute -left-3 flex items-center justify-center w-6 h-6 rounded-full ring-4 {{ $deliveredAt ? $stepDone : $stepPending }}">
                    @if($deliveredAt)
                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                        </svg>
                    @endif
                </span>
                <div>
                    <p class="text-sm font-semibold text-slate-900">Delivered</p>
                    @if($deliveredAt)
                        <p class="text-xs text-slate-600 mt-0.5">{{ $deliveredAt }}</p>
                    @elseif($isCancelled)
                        <p class="text-xs text-slate-400 mt-0.5 italic">Not delivered</p>
                    @else
                        <p class="text-xs text-slate-400 mt-0.5 italic">Pending</p>
                    @endif
                </div>
            </li>

            @if($isCancelled)
                <li class="ml-6">
                    <span class="absolute -left-3 flex items-center justify-center w-6 h-6 rounded-full ring-4 {{ $stepCancelled }}">
                        <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-slate-900">Cancelled</p>
                        <p class="text-xs text-slate-600 mt-0.5">{{ $cancelledAt ?? 'Cancelled' }}</p>
                    </div>
                </li>
                @if(!empty($delivery->cancellation_reason))
                    <li class="ml-6">
                        <span class="absolute -left-3 flex items-center justify-center w-6 h-6 rounded-full ring-4 {{ $stepCancelled }}">
                            <svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/>
                            </svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-slate-900">Reason</p>
                            <p class="text-xs text-slate-700 mt-0.5 whitespace-pre-wrap">{{ $delivery->cancellation_reason }}</p>
                        </div>
                    </li>
                @endif
            @endif
        </ol>
    </x-card>

    <x-card>
        <form method="POST" action="{{ route('tracking.signOut') }}">
            @csrf
            <x-button type="submit" variant="secondary" class="w-full justify-center">
                Look up another delivery
            </x-button>
        </form>
    </x-card>
@endsection
