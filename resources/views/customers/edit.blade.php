@extends('layouts.app')

@section('title', 'Edit Customer')

@section('content')
    <x-page-header
        title="Edit Customer"
        subtitle="Update customer details. Toggle the active flag to retire a customer without deleting historical data.">
        <x-slot:actions>
            <x-button :href="route('customers.index')" variant="secondary">Back to Customers</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <form method="POST" action="{{ route('customers.update', $customer) }}">
            @csrf
            @method('PUT')
            @include('customers._form', ['customer' => $customer, 'mapConfig' => $mapConfig])
        </form>
    </x-card>
@endsection
