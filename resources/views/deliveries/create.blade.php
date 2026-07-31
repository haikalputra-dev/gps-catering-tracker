@php
    /** @var \App\Models\Delivery $delivery */
@endphp
@extends('layouts.app')

@section('title', 'New Delivery Draft')

@section('content')
    <x-page-header
        title="New Delivery Draft"
        subtitle="Create a draft first, then schedule it from the detail page to lock in the receipt number and kitchen/customer snapshots.">
        <x-slot:actions>
            <x-button :href="route('deliveries.index')" variant="secondary">Back to Deliveries</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <form method="POST" action="{{ route('deliveries.store') }}">
            @csrf
            @include('deliveries._form', [
                'delivery' => $delivery,
                'kitchens' => $kitchens,
                'customers' => $customers,
            ])
            <div class="mt-6 flex items-center gap-3">
                <x-button type="submit" icon="check">Save Draft</x-button>
                <x-button :href="route('deliveries.index')" variant="secondary">Cancel</x-button>
            </div>
        </form>
    </x-card>
@endsection
