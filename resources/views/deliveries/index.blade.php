@php
    use App\Domain\Delivery\DeliveryStatus;

    $displayTz = config('delivery.receipt_date_timezone', 'Asia/Jakarta');
@endphp
@extends('layouts.app')

@section('title', 'Deliveries')

@section('content')
    <x-page-header title="Deliveries" subtitle="All catering runs across kitchens, couriers, and customers.">
        <x-slot:actions>
            <x-button :href="route('deliveries.create')">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                New Delivery
            </x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <form method="GET" action="{{ route('deliveries.index') }}" class="flex items-end gap-3 flex-wrap">
            <div class="min-w-[220px]">
                <x-form-field name="status" label="Filter by status" type="select" onchange="this.form.submit()">
                    <option value="">All statuses</option>
                    @foreach($statusOptions as $option)
                        <option value="{{ $option->value }}"
                            @selected($statusFilter?->value === $option->value)>
                            {{ $option->label() }}
                        </option>
                    @endforeach
                </x-form-field>
            </div>
            @if($statusFilter)
                <x-button :href="route('deliveries.index')" variant="secondary">Clear</x-button>
            @endif
        </form>
    </x-card>

    @if($deliveries->isEmpty())
        <x-card>
            <div class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-slate-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177V16.5m0 0h-3M14.25 7.573a2.25 2.25 0 0 1 4.5 0" />
                </svg>
                <h3 class="mt-2 text-sm font-semibold text-slate-900">No deliveries match the current filter.</h3>
                <p class="mt-1 text-sm text-slate-500">Adjust the filter or create a new delivery draft.</p>
            </div>
        </x-card>
    @else
        <x-card padding="p-0">
            <x-table :headers="['ID', 'Receipt', 'Status', 'Kitchen', 'Customer', 'Courier', 'Scheduled (' . $displayTz . ')', 'Fee', 'Created', 'Actions']">
                @foreach($deliveries as $delivery)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-900 whitespace-nowrap">#{{ $delivery->id }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if($delivery->receipt_number)
                                <code class="text-xs bg-slate-100 rounded px-1.5 py-0.5 text-slate-800">{{ $delivery->receipt_number }}</code>
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @include('deliveries._status_badge', ['status' => $delivery->status])
                        </td>
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap">
                            @if($delivery->status === DeliveryStatus::Draft)
                                {{ $delivery->kitchen?->code }} — {{ $delivery->kitchen?->name }}
                            @else
                                {{ $delivery->kitchen_code }} — {{ $delivery->kitchen_name }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap">
                            @if($delivery->status === DeliveryStatus::Draft)
                                {{ $delivery->customer?->name }}
                            @else
                                {{ $delivery->customer_name }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap">
                            @if($delivery->courier)
                                {{ $delivery->courier->name }}
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap">
                            @if($delivery->scheduled_at)
                                {{ $delivery->scheduled_at->copy()->setTimezone($displayTz)->format('Y-m-d H:i') }}
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap">
                            @if($delivery->fee_rupiah !== null)
                                Rp {{ number_format((int) $delivery->fee_rupiah, 0, ',', '.') }}
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ optional($delivery->created_at)->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <a href="{{ route('deliveries.show', $delivery) }}"
                               class="text-orange-600 hover:text-orange-700 font-medium text-sm">
                                View
                            </a>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
        <div class="mt-4">
            {{ $deliveries->links() }}
        </div>
    @endif
@endsection
