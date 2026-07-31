@extends('layouts.app')

@section('title', 'New Kitchen')

@section('content')
    <x-page-header
        title="Add Kitchen"
        subtitle="Register a new pickup location.">
        <x-slot:actions>
            <x-button :href="route('kitchens.index')" variant="secondary">Back to Kitchens</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <form method="POST" action="{{ route('kitchens.store') }}">
            @csrf
            @include('kitchens._form', ['kitchen' => $kitchen, 'mapConfig' => $mapConfig])
        </form>
    </x-card>
@endsection
