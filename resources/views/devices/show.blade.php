@extends('layouts.app')

@section('title', 'Device: ' . $device->identifier)

@section('content')
    <x-page-header
        :title="$device->identifier"
        :subtitle="($device->model ?? 'No model recorded') . ($device->hardware_version ? ' · ' . $device->hardware_version : '')">
        <x-slot:actions>
            <x-button :href="route('devices.edit', $device)" variant="secondary">Edit</x-button>
            <x-button :href="route('devices.index')" variant="secondary">Back</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card title="Details">
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <dt class="font-medium text-slate-700">Status</dt>
                <dd class="mt-1">
                    @if($device->is_active)
                        <x-badge variant="success">Active</x-badge>
                    @else
                        <x-badge variant="danger">Inactive</x-badge>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="font-medium text-slate-700">Last seen</dt>
                <dd class="mt-1 text-slate-900">{{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</dd>
            </div>
            @if($device->notes)
                <div class="sm:col-span-2">
                    <dt class="font-medium text-slate-700">Notes</dt>
                    <dd class="mt-1 text-slate-900">{{ $device->notes }}</dd>
                </div>
            @endif
        </dl>
    </x-card>

    @if(session('token_plain'))
        <x-card class="border-amber-300 bg-amber-50">
            <h2 class="text-base font-semibold text-amber-900">Device API token</h2>
            <p class="mt-2 text-sm text-amber-900">
                <strong>This is the only time this token will be shown.</strong>
                Copy it into the device configuration now. If you lose it, rotate the token to issue a new one.
            </p>
            <pre class="mt-3 bg-white border border-amber-200 rounded p-3 text-xs font-mono overflow-x-auto text-slate-800">{{ session('token_plain') }}</pre>
        </x-card>
    @endif

    <x-card title="API token">
        <p class="text-sm text-slate-700">
            <strong class="font-medium text-slate-700">Token preview:</strong>
            <code class="ml-1 bg-slate-100 rounded px-1.5 py-0.5 text-xs text-slate-800">{{ $device->tokenPreview() }}</code>
        </p>
        <form method="POST" action="{{ route('devices.rotate-token', $device) }}"
              class="mt-4 inline-block"
              onsubmit="return confirm('Rotating will invalidate the current token. Continue?');">
            @csrf
            <x-button type="submit" variant="secondary">Rotate token</x-button>
        </form>
    </x-card>

    <x-card title="Courier binding">
        @if($device->currentAssignment && $device->currentAssignment->courier)
            <p class="text-sm text-slate-700">
                <strong class="font-medium text-slate-700">Currently bound to:</strong>
                <span class="text-slate-900">{{ $device->currentAssignment->courier->name }}</span>
                <span class="text-slate-500">(since {{ $device->currentAssignment->assigned_at?->diffForHumans() }})</span>
            </p>
            <form method="POST" action="{{ route('devices.unassign', $device) }}"
                  class="mt-4 inline-block"
                  onsubmit="return confirm('Unassign this device from the courier?');">
                @csrf
                <x-button type="submit" variant="secondary">Unassign</x-button>
            </form>
        @else
            <p class="text-sm text-slate-500">This device is not currently bound to any courier.</p>

            @if($device->is_active)
                <form method="POST" action="{{ route('devices.assign', $device) }}" class="mt-4 space-y-4">
                    @csrf
                    <x-form-field name="courier_id" label="Assign to courier" type="select" :required="true">
                        <option value="">— Select active courier —</option>
                        @foreach($activeCouriers as $courier)
                            <option value="{{ $courier->id }}" @selected(old('courier_id') == $courier->id)>
                                {{ $courier->name }} ({{ $courier->email }})
                            </option>
                        @endforeach
                    </x-form-field>

                    <x-form-field
                        name="notes"
                        label="Notes (optional)"
                        maxlength="500" />

                    <x-button type="submit">Assign</x-button>
                </form>
            @else
                <p class="mt-2 text-sm text-slate-500">Reactivate this device to enable courier binding.</p>
            @endif
        @endif
    </x-card>

    <x-card title="Assignment history" padding="p-0">
        @if($device->assignments->isEmpty())
            <p class="p-6 text-sm text-slate-500">No assignments recorded yet.</p>
        @else
            <x-table :headers="['Courier', 'Assigned', 'Unassigned', 'By', 'Notes']">
                @foreach($device->assignments as $assignment)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-slate-900 whitespace-nowrap">{{ $assignment->courier?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ $assignment->assigned_at?->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap">
                            @if($assignment->unassigned_at)
                                {{ $assignment->unassigned_at->format('Y-m-d H:i') }}
                            @else
                                <em class="text-slate-500">Open</em>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap">
                            {{ $assignment->assignedBy?->name ?? '—' }}
                            @if($assignment->unassignedBy)
                                <span class="text-slate-400">→</span> {{ $assignment->unassignedBy->name }}
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $assignment->notes ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-table>
        @endif
    </x-card>
@endsection
