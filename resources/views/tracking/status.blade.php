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
@endphp

@section('content')
    <div class="card">
        <h1 style="margin-bottom:8px;">Delivery status</h1>
        <p style="margin-top:0;">
            <span class="status-badge status-{{ $statusValue }}">{{ $status->label() }}</span>
        </p>
        <dl class="summary">
            <dt>Receipt</dt>
            <dd><code>{{ $delivery->receipt_number }}</code></dd>
        </dl>
    </div>

    <div class="card">
        <h2>From</h2>
        <dl class="summary">
            <dt>Kitchen</dt>
            <dd>{{ $delivery->kitchen_name }}</dd>
            <dt>Address</dt>
            <dd>{{ $delivery->kitchen_address }}</dd>
        </dl>
    </div>

    <div class="card">
        <h2>To</h2>
        <dl class="summary">
            <dt>Recipient</dt>
            <dd>{{ $delivery->customer_name }}</dd>
            <dt>Address</dt>
            <dd>{{ $delivery->customer_address }}</dd>
        </dl>
    </div>

    <div class="card">
        <h2>Distance and fee</h2>
        <dl class="summary">
            <dt>Distance</dt>
            <dd>{{ number_format((float) $delivery->distance_km, 2, '.', ',') }} km</dd>
            <dt>Delivery fee</dt>
            <dd>Rp {{ number_format((int) $delivery->fee_rupiah, 0, ',', '.') }}</dd>
        </dl>
    </div>

    @if($isInTransit && $delivery->courier)
        <div class="card">
            <h2>Courier</h2>
            <dl class="summary">
                <dt>Name</dt>
                <dd>{{ $delivery->courier->name }}</dd>
                @if(!empty($delivery->courier->phone))
                    <dt>Phone</dt>
                    <dd>{{ $delivery->courier->phone }}</dd>
                @endif
            </dl>
            <p class="footer-note" style="text-align:left;margin:8px 0 0;">Your courier is on the way.</p>
        </div>
    @endif

    <div class="card">
        <h2>Timeline</h2>
        <ol class="timeline">
            <li>
                <span class="label">Scheduled</span>
                @if($scheduledAt)
                    <span class="done">{{ $scheduledAt }}</span>
                @else
                    <span class="pending">Pending</span>
                @endif
            </li>
            <li>
                <span class="label">Dispatched</span>
                @if($dispatchedAt)
                    <span class="done">{{ $dispatchedAt }}</span>
                @elseif($isCancelled && !$dispatchedAt)
                    <span class="pending">Not dispatched</span>
                @else
                    <span class="pending">Pending</span>
                @endif
            </li>
            <li>
                <span class="label">Delivered</span>
                @if($deliveredAt)
                    <span class="done">{{ $deliveredAt }}</span>
                @elseif($isCancelled)
                    <span class="pending">Not delivered</span>
                @else
                    <span class="pending">Pending</span>
                @endif
            </li>
            @if($isCancelled)
                <li>
                    <span class="label">Cancelled</span>
                    <span class="cancelled">{{ $cancelledAt ?? 'Cancelled' }}</span>
                </li>
                @if(!empty($delivery->cancellation_reason))
                    <li>
                        <span class="label">Reason</span>
                        <span>{{ $delivery->cancellation_reason }}</span>
                    </li>
                @endif
            @endif
        </ol>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('tracking.signOut') }}">
            @csrf
            <button type="submit" class="secondary">Look up another delivery</button>
        </form>
    </div>
@endsection
