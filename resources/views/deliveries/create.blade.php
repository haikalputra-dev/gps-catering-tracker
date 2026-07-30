@php
    /** @var \App\Models\Delivery $delivery */
@endphp
@extends('layouts.app')

@section('title', 'New Delivery Draft')

@section('content')
    <div class="card">
        <h1 style="margin:0 0 12px;font-size:1.4rem;">New Delivery Draft</h1>
        <p style="margin:0;color:#6b7280;">
            Create a draft first, then schedule it from the detail page to lock in
            the receipt number and kitchen/customer snapshots.
        </p>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('deliveries.store') }}">
            @csrf
            @include('deliveries._form', [
                'delivery' => $delivery,
                'kitchens' => $kitchens,
                'customers' => $customers,
            ])
            <div style="margin-top:16px;">
                <button type="submit">Save Draft</button>
                <a class="btn secondary" href="{{ route('deliveries.index') }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection
