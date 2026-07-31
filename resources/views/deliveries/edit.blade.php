@php
    /** @var \App\Models\Delivery $delivery */
@endphp
@extends('layouts.app')

@section('title', 'Edit Delivery Draft')

@section('content')
    <x-page-header
        :title="'Edit Delivery Draft #' . $delivery->id"
        subtitle="Editing is only allowed while the delivery is still a draft. Once scheduled, kitchen and customer details are snapshotted and become immutable.">
        <x-slot:actions>
            @include('deliveries._status_badge', ['status' => $delivery->status])
            <x-button :href="route('deliveries.show', $delivery)" variant="secondary">Back</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <form method="POST" action="{{ route('deliveries.update', $delivery) }}">
            @csrf
            @method('PUT')
            @include('deliveries._form', [
                'delivery' => $delivery,
                'kitchens' => $kitchens,
                'customers' => $customers,
            ])
            <div class="mt-6 flex items-center gap-3">
                <x-button type="submit">Save Changes</x-button>
                <x-button :href="route('deliveries.show', $delivery)" variant="secondary">Cancel</x-button>
            </div>
        </form>
    </x-card>
@endsection
