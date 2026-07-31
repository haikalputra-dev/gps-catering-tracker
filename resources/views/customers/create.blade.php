@extends('layouts.app')

@section('title', 'Add Customer')

@section('content')
    <x-page-header
        title="Add Customer"
        subtitle="Create a new customer record. The phone number must be unique.">
        <x-slot:actions>
            <x-button :href="route('customers.index')" variant="secondary">Back to Customers</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <form method="POST" action="{{ route('customers.store') }}">
            @csrf
            @include('customers._form', ['customer' => $customer, 'mapConfig' => $mapConfig])
        </form>
    </x-card>
@endsection
