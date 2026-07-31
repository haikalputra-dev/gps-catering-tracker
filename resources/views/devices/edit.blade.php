@extends('layouts.app')

@section('title', 'Edit device')

@section('content')
    <x-page-header
        title="Edit device"
        :subtitle="$device->identifier">
        <x-slot:actions>
            <x-button :href="route('devices.show', $device)" variant="secondary">Back to Device</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <form method="POST" action="{{ route('devices.update', $device) }}">
            @csrf
            @method('PUT')
            @include('devices._form', ['device' => $device])
            <div class="mt-6 flex items-center gap-3">
                <x-button type="submit" icon="check">Save changes</x-button>
                <x-button :href="route('devices.show', $device)" variant="secondary">Cancel</x-button>
            </div>
        </form>
    </x-card>
@endsection
