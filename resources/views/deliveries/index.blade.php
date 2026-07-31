@php
    use App\Domain\Delivery\DeliveryStatus;

    $displayTz = config('delivery.receipt_date_timezone', 'Asia/Jakarta');
@endphp
@extends('layouts.app')

@section('title', 'Deliveries')

@section('content')
    <x-page-header title="Deliveries" subtitle="All catering runs across kitchens, couriers, and customers.">
        <x-slot:actions>
            <x-button :href="route('deliveries.create')" icon="plus">
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
            @if($statusFilter)
                <x-empty-state
                    icon="funnel"
                    title="No deliveries match the current filter."
                    description="Adjust the filter or create a new delivery draft."
                    actionLabel="Clear filter"
                    :actionHref="route('deliveries.index')"
                    actionIcon="x-mark"
                />
            @else
                <x-empty-state
                    icon="truck"
                    title="No deliveries yet."
                    description="Schedule your first delivery to start dispatching couriers."
                    actionLabel="New Delivery"
                    :actionHref="route('deliveries.create')"
                />
            @endif
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
                               class="text-red-600 hover:text-red-700 font-medium text-sm">
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
