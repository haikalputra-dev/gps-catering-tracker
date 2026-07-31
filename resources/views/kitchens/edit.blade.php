@extends('layouts.app')

@section('title', 'Edit Kitchen')

@section('content')
    <x-page-header
        title="Edit Kitchen"
        :subtitle="$kitchen->code . ' — ' . $kitchen->name">
        <x-slot:actions>
            <x-button :href="route('kitchens.index')" variant="secondary">Back to Kitchens</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <form method="POST" action="{{ route('kitchens.update', $kitchen) }}">
            @csrf
            @method('PUT')
            @include('kitchens._form', ['kitchen' => $kitchen, 'mapConfig' => $mapConfig])
        </form>
    </x-card>
@endsection
