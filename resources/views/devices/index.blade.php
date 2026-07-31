@extends('layouts.app')

@section('title', 'Devices')

@section('content')
    <x-page-header
        title="Devices"
        subtitle="Physical GPS trackers registered to this tenant. Deactivate to revoke ingest access; devices are never deleted.">
        <x-slot:actions>
            <x-button :href="route('devices.create')" icon="plus">
                Register device
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if($devices->isEmpty())
        <x-card>
            <div class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-slate-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                </svg>
                <h3 class="mt-2 text-sm font-semibold text-slate-900">No devices registered yet.</h3>
                <p class="mt-1 text-sm text-slate-500">Register a GPS tracker to start ingesting telemetry.</p>
                <div class="mt-6">
                    <x-button :href="route('devices.create')">Register device</x-button>
                </div>
            </div>
        </x-card>
    @else
        <x-card padding="p-0">
            <x-table :headers="['Identifier', 'Model', 'Assigned courier', 'Status', 'Last seen', '']">
                @foreach($devices as $device)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="font-semibold text-slate-900">{{ $device->identifier }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ $device->model ?? '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if($device->currentAssignment && $device->currentAssignment->courier)
                                <span class="text-slate-900">{{ $device->currentAssignment->courier->name }}</span>
                            @else
                                <span class="text-slate-400">Unassigned</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if($device->is_active)
                                <x-badge variant="success">Active</x-badge>
                            @else
                                <x-badge variant="danger">Inactive</x-badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <a href="{{ route('devices.show', $device) }}"
                               class="text-orange-600 hover:text-orange-700 font-medium text-sm">
                                View
                            </a>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif
@endsection
