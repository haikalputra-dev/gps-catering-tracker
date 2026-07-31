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
    <x-page-header title="Customers" subtitle="Delivery recipients on record.">
        <x-slot:actions>
            <x-button :href="route('customers.create')" icon="plus">
                Add Customer
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if($customers->isEmpty())
        <x-card>
            <x-empty-state
                icon="users"
                title="No customers have been added yet."
                description="Add a customer to schedule their first delivery."
                actionLabel="Add Customer"
                :actionHref="route('customers.create')"
            />
        </x-card>
    @else
        <x-card padding="p-0">
            <x-table :headers="['Name', 'Phone', 'Address', 'Latitude', 'Longitude', 'Status', 'Created', 'Actions']">
                @foreach($customers as $customer)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-900 whitespace-nowrap">{{ $customer->name }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="customer-phone-masked font-mono text-xs text-slate-600" title="Phone is masked for privacy">
                                {{ $maskPhone($customer->phone) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ \Illuminate\Support\Str::limit($customer->address, 60) }}</td>
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap font-mono text-xs">{{ $customer->latitude }}</td>
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap font-mono text-xs">{{ $customer->longitude }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if($customer->is_active)
                                <x-badge variant="success">Active</x-badge>
                            @else
                                <x-badge variant="danger">Inactive</x-badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ optional($customer->created_at)->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <a href="{{ route('customers.edit', $customer) }}"
                               class="text-red-600 hover:text-red-700 font-medium text-sm">
                                Edit
                            </a>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
        <div class="mt-4">
            {{ $customers->links() }}
        </div>
    @endif
@endsection
