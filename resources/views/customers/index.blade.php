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
            <x-button :href="route('customers.create')">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Add Customer
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if($customers->isEmpty())
        <x-card>
            <div class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-slate-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                </svg>
                <h3 class="mt-2 text-sm font-semibold text-slate-900">No customers have been added yet.</h3>
                <p class="mt-1 text-sm text-slate-500">Add a customer to schedule their first delivery.</p>
                <div class="mt-6">
                    <x-button :href="route('customers.create')">Add Customer</x-button>
                </div>
            </div>
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
                               class="text-orange-600 hover:text-orange-700 font-medium text-sm">
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
