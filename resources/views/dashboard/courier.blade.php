@php
    /** @var \App\Models\Delivery|null $activeDelivery */
    use App\Domain\Delivery\DeliveryStatus;

    $displayTz = config('delivery.receipt_date_timezone', 'Asia/Jakarta');
@endphp
@extends('layouts.app')

@section('title', 'Courier Dashboard')

@section('content')
    <div class="card">
        <h1 style="margin:0;font-size:1.4rem;">Courier Dashboard</h1>
        <p style="margin:6px 0 0;">Welcome, {{ auth()->user()->name }}.</p>
    </div>

    @if($activeDelivery === null)
        <div class="card">
            <h2 style="margin:0 0 8px;font-size:1.1rem;">No active delivery</h2>
            <p style="margin:0;color:#6b7280;">
                You have no delivery assigned right now. Check back later or ask
                the office to assign one to you.
            </p>
        </div>
    @else
        <div class="card">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                <div>
                    <h2 style="margin:0;font-size:1.1rem;">
                        Delivery #{{ $activeDelivery->id }}
                        @if($activeDelivery->receipt_number)
                            <small style="color:#6b7280;font-weight:normal;">
                                &middot; <code>{{ $activeDelivery->receipt_number }}</code>
                            </small>
                        @endif
                    </h2>
                    <div style="margin-top:6px;">
                        @include('deliveries._status_badge', ['status' => $activeDelivery->status])
                    </div>
                </div>
                <div>
                    <a class="btn secondary"
                       href="{{ route('deliveries.show', $activeDelivery) }}">
                        View details
                    </a>
                </div>
            </div>
        </div>

        <div class="card">
            <h2 style="margin:0 0 8px;font-size:1.1rem;">Pickup</h2>
            <p style="margin:0;">
                <strong>{{ $activeDelivery->kitchen_code }}</strong> —
                {{ $activeDelivery->kitchen_name }}<br>
                <span style="color:#4b5563;">{{ $activeDelivery->kitchen_address }}</span>
            </p>
        </div>

        <div class="card">
            <h2 style="margin:0 0 8px;font-size:1.1rem;">Drop-off</h2>
            <p style="margin:0;">
                <strong>{{ $activeDelivery->customer_name }}</strong>
                ({{ $activeDelivery->customer_phone }})<br>
                <span style="color:#4b5563;">{{ $activeDelivery->customer_address }}</span>
            </p>
        </div>

        <div class="card">
            <h2 style="margin:0 0 8px;font-size:1.1rem;">Schedule</h2>
            <p style="margin:0;">
                <strong>Scheduled for:</strong>
                @if($activeDelivery->scheduled_at)
                    {{ $activeDelivery->scheduled_at->copy()->setTimezone($displayTz)->format('Y-m-d H:i') }}
                    <small style="color:#6b7280;">({{ $displayTz }})</small>
                @else
                    <span class="placeholder">Not set</span>
                @endif
            </p>
            @if($activeDelivery->status === DeliveryStatus::InTransit && $activeDelivery->dispatched_at)
                <p style="margin:8px 0 0;">
                    <strong>Dispatched at:</strong>
                    {{ $activeDelivery->dispatched_at->copy()->setTimezone($displayTz)->format('Y-m-d H:i') }}
                    <small style="color:#6b7280;">({{ $displayTz }})</small>
                </p>
            @endif
            @if($activeDelivery->notes)
                <p style="margin:8px 0 0;">
                    <strong>Notes:</strong><br>
                    <span style="white-space:pre-wrap;">{{ $activeDelivery->notes }}</span>
                </p>
            @endif
        </div>

        {{--
            Fee is intentionally omitted from the courier dashboard (AR-40).
            The Pricing card is DOM-absent for couriers, so the fee never
            reaches the browser for a courier session.
        --}}

        <div class="card">
            @include('deliveries._action_buttons', ['delivery' => $activeDelivery])
        </div>
    @endif
@endsection
