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
            <x-empty-state
                icon="device-phone-mobile"
                title="No devices registered yet."
                description="Register a GPS tracker to start ingesting telemetry."
                actionLabel="Register device"
                :actionHref="route('devices.create')"
            />
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
