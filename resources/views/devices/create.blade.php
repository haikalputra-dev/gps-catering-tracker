@extends('layouts.app')

@section('title', 'Register device')

@section('content')
    <x-page-header
        title="Register device"
        subtitle="Add a new GPS tracker to this tenant.">
        <x-slot:actions>
            <x-button :href="route('devices.index')" variant="secondary">Back to Devices</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <form method="POST" action="{{ route('devices.store') }}">
            @csrf
            @include('devices._form', ['device' => null])
            <div class="mt-6 flex items-center gap-3">
                <x-button type="submit" icon="check">Register device</x-button>
                <x-button :href="route('devices.index')" variant="secondary">Cancel</x-button>
            </div>
        </form>
    </x-card>
@endsection
