@extends('layouts.app')

@section('title', 'Kitchens')

@section('content')
    <x-page-header title="Kitchens" subtitle="Pickup locations available for scheduling.">
        <x-slot:actions>
            <x-button :href="route('kitchens.create')" icon="plus">
                Add Kitchen
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if($kitchens->isEmpty())
        <x-card>
            <x-empty-state
                icon="building-storefront"
                title="No kitchens have been added yet."
                description="Get started by registering a pickup location where couriers can collect orders."
                actionLabel="Add Kitchen"
                :actionHref="route('kitchens.create')"
            />
        </x-card>
    @else
        <x-card padding="p-0">
            <x-table :headers="['Code', 'Name', 'Address', 'Phone', 'Latitude', 'Longitude', 'Status', 'Created', 'Actions']">
                @foreach($kitchens as $kitchen)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <code class="text-xs bg-slate-100 rounded px-1.5 py-0.5 text-slate-800">{{ $kitchen->code }}</code>
                        </td>
                        <td class="px-4 py-3 font-medium text-slate-900">{{ $kitchen->name }}</td>
                        <td class="px-4 py-3 text-slate-600 max-w-xs truncate">{{ $kitchen->address }}</td>
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ $kitchen->phone ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap font-mono text-xs">{{ $kitchen->latitude }}</td>
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap font-mono text-xs">{{ $kitchen->longitude }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if($kitchen->is_active)
                                <x-badge variant="success">Active</x-badge>
                            @else
                                <x-badge variant="danger">Inactive</x-badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ optional($kitchen->created_at)->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <a href="{{ route('kitchens.edit', $kitchen) }}"
                               class="text-orange-600 hover:text-orange-700 font-medium text-sm">
                                Edit
                            </a>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
        <div class="mt-4">
            {{ $kitchens->links() }}
        </div>
    @endif
@endsection
