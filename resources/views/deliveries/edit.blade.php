@php
    /** @var \App\Models\Delivery $delivery */
@endphp
@extends('layouts.app')

@section('title', 'Edit Delivery Draft')

@section('content')
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <h1 style="margin:0;font-size:1.4rem;">Edit Delivery Draft #{{ $delivery->id }}</h1>
            @include('deliveries._status_badge', ['status' => $delivery->status])
        </div>
        <p style="margin:8px 0 0;color:#6b7280;">
            Editing is only allowed while the delivery is still a draft. Once
            scheduled, kitchen and customer details are snapshotted and become
            immutable.
        </p>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('deliveries.update', $delivery) }}">
            @csrf
            @method('PUT')
            @include('deliveries._form', [
                'delivery' => $delivery,
                'kitchens' => $kitchens,
                'customers' => $customers,
            ])
            <div style="margin-top:16px;">
                <button type="submit">Save Changes</button>
                <a class="btn secondary" href="{{ route('deliveries.show', $delivery) }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection
