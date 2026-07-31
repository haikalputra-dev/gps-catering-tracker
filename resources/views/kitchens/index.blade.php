@extends('layouts.app')

@section('title', 'Kitchens')

@section('content')
    <x-page-header title="Kitchens" subtitle="Pickup locations available for scheduling.">
        <x-slot:actions>
            <x-button :href="route('kitchens.create')">
                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Add Kitchen
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if($kitchens->isEmpty())
        <x-card>
            <div class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-slate-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a.75.75 0 0 1 1.06 0L21.219 12M4.5 9.75v10.125A1.125 1.125 0 0 0 5.625 21H9.75v-4.875a1.125 1.125 0 0 1 1.125-1.125h2.25a1.125 1.125 0 0 1 1.125 1.125V21h4.125a1.125 1.125 0 0 0 1.125-1.125V9.75" />
                </svg>
                <h3 class="mt-2 text-sm font-semibold text-slate-900">No kitchens have been added yet.</h3>
                <p class="mt-1 text-sm text-slate-500">Get started by registering a pickup location.</p>
                <div class="mt-6">
                    <x-button :href="route('kitchens.create')">Add Kitchen</x-button>
                </div>
            </div>
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
