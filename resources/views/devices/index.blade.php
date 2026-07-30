@extends('layouts.app')

@section('title', 'Devices')

@section('content')
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h1>Devices</h1>
            <a href="{{ route('devices.create') }}" class="btn">Register device</a>
        </div>
        <p class="placeholder">Physical GPS trackers registered to this tenant. Deactivate to revoke ingest access; devices are never deleted.</p>

        @if($devices->isEmpty())
            <p>No devices registered yet.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Identifier</th>
                        <th>Model</th>
                        <th>Assigned courier</th>
                        <th>Status</th>
                        <th>Last seen</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($devices as $device)
                        <tr>
                            <td><strong>{{ $device->identifier }}</strong></td>
                            <td>{{ $device->model ?? '—' }}</td>
                            <td>
                                @if($device->currentAssignment && $device->currentAssignment->courier)
                                    {{ $device->currentAssignment->courier->name }}
                                @else
                                    <span class="placeholder">Unassigned</span>
                                @endif
                            </td>
                            <td>{{ $device->is_active ? 'Active' : 'Inactive' }}</td>
                            <td>{{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</td>
                            <td><a href="{{ route('devices.show', $device) }}">View</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
