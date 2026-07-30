@extends('layouts.app')

@section('title', 'Edit Customer')

@section('content')
    <div class="card">
        <h1 style="margin:0 0 12px 0;font-size:1.4rem;">Edit Customer</h1>
        <p class="placeholder" style="margin:0;">
            Update customer details. Toggle the active flag to retire a
            customer without deleting historical data.
        </p>
    </div>

    @if($errors->any())
        <div class="card" style="background:#fef2f2;color:#991b1b;">
            <strong>Please fix the following:</strong>
            <ul style="margin:8px 0 0 20px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <form method="POST" action="{{ route('customers.update', $customer) }}">
            @csrf
            @method('PUT')
            @include('customers._form', ['customer' => $customer, 'mapConfig' => $mapConfig])
        </form>
    </div>
@endsection
