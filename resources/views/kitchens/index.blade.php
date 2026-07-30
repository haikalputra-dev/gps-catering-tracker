@extends('layouts.app')

@section('title', 'Kitchens')

@section('content')
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <h1 style="margin:0;font-size:1.4rem;">Kitchens</h1>
            <a class="btn" href="{{ route('kitchens.create') }}">Add Kitchen</a>
        </div>
    </div>

    <div class="card">
        @if($kitchens->isEmpty())
            <p class="placeholder">No kitchens have been added yet.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Address</th>
                        <th>Phone</th>
                        <th>Latitude</th>
                        <th>Longitude</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($kitchens as $kitchen)
                        <tr>
                            <td><code>{{ $kitchen->code }}</code></td>
                            <td>{{ $kitchen->name }}</td>
                            <td>{{ $kitchen->address }}</td>
                            <td>{{ $kitchen->phone ?? '—' }}</td>
                            <td>{{ $kitchen->latitude }}</td>
                            <td>{{ $kitchen->longitude }}</td>
                            <td>
                                @if($kitchen->is_active)
                                    <span style="padding:2px 8px;border-radius:12px;background:#d1fae5;color:#065f46;font-size:0.8rem;">Active</span>
                                @else
                                    <span style="padding:2px 8px;border-radius:12px;background:#fee2e2;color:#991b1b;font-size:0.8rem;">Inactive</span>
                                @endif
                            </td>
                            <td>{{ optional($kitchen->created_at)->format('Y-m-d') }}</td>
                            <td>
                                <a href="{{ route('kitchens.edit', $kitchen) }}">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div style="margin-top:12px;">
                {{ $kitchens->links() }}
            </div>
        @endif
    </div>
@endsection
