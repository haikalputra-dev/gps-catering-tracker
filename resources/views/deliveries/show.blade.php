@php
    /** @var \App\Models\Delivery $delivery */
    use App\Domain\Delivery\DeliveryStatus;

    $displayTz = config('delivery.receipt_date_timezone', 'Asia/Jakarta');
@endphp
@extends('layouts.app')

@section('title', 'Delivery #' . $delivery->id)

@section('content')
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <div>
                <h1 style="margin:0;font-size:1.4rem;">
                    Delivery #{{ $delivery->id }}
                    @if($delivery->receipt_number)
                        <small style="color:#6b7280;font-weight:normal;">
                            &middot; <code>{{ $delivery->receipt_number }}</code>
                        </small>
                    @endif
                </h1>
                <div style="margin-top:6px;">
                    @include('deliveries._status_badge', ['status' => $delivery->status])
                </div>
            </div>
            <div>
                <a class="btn secondary" href="{{ route('deliveries.index') }}">Back to list</a>
            </div>
        </div>
    </div>

    <div class="card">
        <h2 style="margin:0 0 8px;font-size:1.1rem;">Kitchen</h2>
        @if($delivery->status === DeliveryStatus::Draft)
            <p style="margin:0;color:#6b7280;font-style:italic;">
                Live reference (snapshot captured at scheduling):
            </p>
            <p style="margin:6px 0 0;">
                <strong>{{ $delivery->kitchen?->code }}</strong> — {{ $delivery->kitchen?->name }}<br>
                <span style="color:#4b5563;">{{ $delivery->kitchen?->address }}</span>
            </p>
        @else
            <p style="margin:0;">
                <strong>{{ $delivery->kitchen_code }}</strong> — {{ $delivery->kitchen_name }}<br>
                <span style="color:#4b5563;">{{ $delivery->kitchen_address }}</span><br>
                <small style="color:#6b7280;">
                    Lat/Lng: {{ $delivery->kitchen_latitude }}, {{ $delivery->kitchen_longitude }}
                </small>
            </p>
        @endif
    </div>

    <div class="card">
        <h2 style="margin:0 0 8px;font-size:1.1rem;">Customer</h2>
        @if($delivery->status === DeliveryStatus::Draft)
            <p style="margin:0;color:#6b7280;font-style:italic;">
                Live reference (snapshot captured at scheduling):
            </p>
            <p style="margin:6px 0 0;">
                <strong>{{ $delivery->customer?->name }}</strong> ({{ $delivery->customer?->phone }})<br>
                <span style="color:#4b5563;">{{ $delivery->customer?->address }}</span>
            </p>
        @else
            <p style="margin:0;">
                <strong>{{ $delivery->customer_name }}</strong> ({{ $delivery->customer_phone }})<br>
                <span style="color:#4b5563;">{{ $delivery->customer_address }}</span><br>
                <small style="color:#6b7280;">
                    Lat/Lng: {{ $delivery->customer_latitude }}, {{ $delivery->customer_longitude }}
                </small>
            </p>
        @endif
    </div>

    <div class="card">
        <h2 style="margin:0 0 8px;font-size:1.1rem;">Courier</h2>
        @if($delivery->courier)
            <p style="margin:0;">
                <strong>{{ $delivery->courier->name }}</strong>
                <small style="color:#6b7280;">(#{{ $delivery->courier->id }})</small>
            </p>
        @else
            <p style="margin:0;color:#6b7280;font-style:italic;">
                No courier assigned yet.
            </p>
        @endif
        <p style="margin:8px 0 0;">
            <strong>Dispatched at:</strong>
            @if($delivery->dispatched_at)
                {{ $delivery->dispatched_at->copy()->setTimezone($displayTz)->format('Y-m-d H:i') }}
                <small style="color:#6b7280;">({{ $displayTz }})</small>
            @else
                <span class="placeholder">—</span>
            @endif
        </p>
        <p style="margin:6px 0 0;">
            <strong>Delivered at:</strong>
            @if($delivery->delivered_at)
                {{ $delivery->delivered_at->copy()->setTimezone($displayTz)->format('Y-m-d H:i') }}
                <small style="color:#6b7280;">({{ $displayTz }})</small>
            @else
                <span class="placeholder">—</span>
            @endif
        </p>
    </div>

    <div class="card">
        <h2 style="margin:0 0 8px;font-size:1.1rem;">Schedule</h2>
        <p style="margin:0;">
            <strong>Scheduled for:</strong>
            @if($delivery->scheduled_at)
                {{ $delivery->scheduled_at->copy()->setTimezone($displayTz)->format('Y-m-d H:i') }}
                <small style="color:#6b7280;">({{ $displayTz }})</small>
            @else
                <span class="placeholder">Not set</span>
            @endif
        </p>
        @if($delivery->notes)
            <p style="margin:8px 0 0;">
                <strong>Notes:</strong><br>
                <span style="white-space:pre-wrap;">{{ $delivery->notes }}</span>
            </p>
        @endif
    </div>

    {{--
        Pricing block is hidden from courier viewers (AR-40). Rendered as
        DOM-absent (not CSS-hidden) so the fee never reaches the browser
        for a courier session.
    --}}
    @if(!auth()->user()?->isCourier())
        <div class="card">
            <h2 style="margin:0 0 8px;font-size:1.1rem;">Pricing</h2>
            <p style="margin:0;">
                <strong>Distance:</strong>
                @if($delivery->distance_km !== null)
                    {{ number_format((float) $delivery->distance_km, 3, '.', '') }} km
                @else
                    <span class="placeholder">—</span>
                @endif
            </p>
            <p style="margin:6px 0 0;">
                <strong>Fee:</strong>
                @if($delivery->fee_rupiah !== null)
                    Rp {{ number_format((int) $delivery->fee_rupiah, 0, ',', '.') }}
                @else
                    <span class="placeholder">—</span>
                @endif
            </p>
            <p style="margin:8px 0 0;color:#6b7280;font-size:0.85rem;">
                Fee is calculated once at scheduling using the straight-line distance
                between the kitchen and the customer. It is not recalculated afterwards.
            </p>
        </div>
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
        <div class="card">
            <h2 style="margin:0 0 8px;font-size:1.1rem;">Live position</h2>
            <p style="margin:0 0 8px;color:#6b7280;font-size:0.9rem;">
                @if($delivery->status === DeliveryStatus::Scheduled)
                    Awaiting dispatch — the courier marker appears once the delivery starts.
                @else
                    Auto-refreshing every {{ (int) (config('telemetry.polling_interval_ms', 3000) / 1000) }}s.
                @endif
            </p>
            <div
                id="delivery-live-map"
                class="live-map-container"
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
                class="live-map-status"
                style="margin:8px 0 0;color:#4b5563;font-size:0.85rem;"
            >
                @if($delivery->status === DeliveryStatus::Scheduled)
                    Awaiting first live position.
                @else
                    Waiting for the first live position.
                @endif
            </p>
        </div>
    @endif

    @include('deliveries._audit', ['delivery' => $delivery, 'displayTz' => $displayTz])

    <div class="card">
        @include('deliveries._action_buttons', ['delivery' => $delivery])
    </div>
@endsection
