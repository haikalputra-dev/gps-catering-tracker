@extends('layouts.app')

@section('title', 'Customers')

@php
    /**
     * Mask a stored phone number for display in the customer list.
     * Keeps the first 5 characters and the last 4 digits so operators
     * can still recognise a record without exposing the full number.
     */
    $maskPhone = static function (?string $phone): string {
        if ($phone === null || $phone === '') {
            return '—';
        }
        $length = strlen($phone);
        if ($length <= 9) {
            return str_repeat('•', max(0, $length - 4)).substr($phone, -4);
        }
        $prefix = substr($phone, 0, 5);
        $suffix = substr($phone, -4);
        $middleLength = max(4, $length - 9);
        return $prefix.str_repeat('•', $middleLength).$suffix;
    };
@endphp

@section('content')
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <h1 style="margin:0;font-size:1.4rem;">Customers</h1>
            <a class="btn" href="{{ route('customers.create') }}">Add Customer</a>
        </div>
    </div>

    @if(session('status'))
        <div class="card" style="background:#ecfdf5;color:#065f46;">
            {{ session('status') }}
        </div>
    @endif

    <div class="card">
        @if($customers->isEmpty())
            <p class="placeholder">No customers have been added yet.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($customers as $customer)
                        <tr>
                            <td>{{ $customer->name }}</td>
                            <td>
                                <span class="customer-phone-masked" title="Phone is masked for privacy">
                                    {{ $maskPhone($customer->phone) }}
                                </span>
                            </td>
                            <td>{{ \Illuminate\Support\Str::limit($customer->address, 60) }}</td>
                            <td>{{ $customer->latitude }}</td>
                            <td>{{ $customer->longitude }}</td>
                            <td>
                                @if($customer->is_active)
                                    <span style="padding:2px 8px;border-radius:12px;background:#d1fae5;color:#065f46;font-size:0.8rem;">Active</span>
                                @else
                                    <span style="padding:2px 8px;border-radius:12px;background:#fee2e2;color:#991b1b;font-size:0.8rem;">Inactive</span>
                                @endif
                            </td>
                            <td>{{ optional($customer->created_at)->format('Y-m-d') }}</td>
                            <td>
                                <a href="{{ route('customers.edit', $customer) }}">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="margin-top:12px;">
                {{ $customers->links() }}
            </div>
        @endif
    </div>
@endsection
