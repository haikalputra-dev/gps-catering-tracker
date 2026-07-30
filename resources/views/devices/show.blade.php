@extends('layouts.app')

@section('title', 'Device: ' . $device->identifier)

@section('content')
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h1 style="margin-bottom: 4px;">{{ $device->identifier }}</h1>
                <p class="placeholder" style="margin-top: 0;">
                    {{ $device->model ?? 'No model recorded' }}
                    @if($device->hardware_version)
                        · {{ $device->hardware_version }}
                    @endif
                </p>
            </div>
            <div>
                <a href="{{ route('devices.edit', $device) }}" class="btn secondary">Edit</a>
                <a href="{{ route('devices.index') }}" class="btn secondary">Back</a>
            </div>
        </div>

        <p><strong>Status:</strong> {{ $device->is_active ? 'Active' : 'Inactive' }}</p>
        <p><strong>Last seen:</strong> {{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</p>

        @if($device->notes)
            <p><strong>Notes:</strong> {{ $device->notes }}</p>
        @endif
    </div>

    @if(session('token_plain'))
        <div class="card" style="border: 1px solid #b45309; background: #fef3c7;">
            <h2 style="margin-top: 0;">Device API token</h2>
            <p><strong>This is the only time this token will be shown.</strong>
               Copy it into the device configuration now. If you lose it, rotate the token to issue a new one.</p>
            <pre style="background: #fff; padding: 10px; border-radius: 4px; overflow-x: auto;">{{ session('token_plain') }}</pre>
        </div>
    @endif

    <div class="card">
        <h2>API token</h2>
        <p><strong>Token preview:</strong> <code>{{ $device->tokenPreview() }}</code></p>
        <form method="POST" action="{{ route('devices.rotate-token', $device) }}" class="inline"
              onsubmit="return confirm('Rotating will invalidate the current token. Continue?');">
            @csrf
            <button type="submit" class="secondary">Rotate token</button>
        </form>
    </div>

    <div class="card">
        <h2>Courier binding</h2>

        @if($device->currentAssignment && $device->currentAssignment->courier)
            <p>
                <strong>Currently bound to:</strong>
                {{ $device->currentAssignment->courier->name }}
                (since {{ $device->currentAssignment->assigned_at?->diffForHumans() }})
            </p>
            <form method="POST" action="{{ route('devices.unassign', $device) }}" class="inline"
                  onsubmit="return confirm('Unassign this device from the courier?');">
                @csrf
                <button type="submit" class="secondary">Unassign</button>
            </form>
        @else
            <p class="placeholder">This device is not currently bound to any courier.</p>

            @if($device->is_active)
                <form method="POST" action="{{ route('devices.assign', $device) }}">
                    @csrf
                    <label for="courier_id">Assign to courier</label>
                    <select id="courier_id" name="courier_id" required>
                        <option value="">— Select active courier —</option>
                        @foreach($activeCouriers as $courier)
                            <option value="{{ $courier->id }}" @selected(old('courier_id') == $courier->id)>
                                {{ $courier->name }} ({{ $courier->email }})
                            </option>
                        @endforeach
                    </select>

                    <label for="notes">Notes (optional)</label>
                    <input type="text" id="notes" name="notes" value="{{ old('notes') }}" maxlength="500">

                    <div style="margin-top: 12px;">
                        <button type="submit">Assign</button>
                    </div>
                </form>
            @else
                <p class="placeholder">Reactivate this device to enable courier binding.</p>
            @endif
        @endif
    </div>

    <div class="card">
        <h2>Assignment history</h2>

        @if($device->assignments->isEmpty())
            <p class="placeholder">No assignments recorded yet.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Courier</th>
                        <th>Assigned</th>
                        <th>Unassigned</th>
                        <th>By</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($device->assignments as $assignment)
                        <tr>
                            <td>{{ $assignment->courier?->name ?? '—' }}</td>
                            <td>{{ $assignment->assigned_at?->format('Y-m-d H:i') }}</td>
                            <td>
                                @if($assignment->unassigned_at)
                                    {{ $assignment->unassigned_at->format('Y-m-d H:i') }}
                                @else
                                    <em>Open</em>
                                @endif
                            </td>
                            <td>
                                {{ $assignment->assignedBy?->name ?? '—' }}
                                @if($assignment->unassignedBy)
                                    → {{ $assignment->unassignedBy->name }}
                                @endif
                            </td>
                            <td>{{ $assignment->notes ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
