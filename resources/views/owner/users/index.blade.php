@extends('layouts.app')

@section('title', 'User Accounts')

@section('content')
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h1>User Accounts</h1>
            <a href="{{ route('owner.users.create') }}" class="btn">Add Account</a>
        </div>
        <p class="placeholder">Only staff and courier accounts are listed. Owner accounts cannot be managed here.</p>

        @if($users->isEmpty())
            <p>No staff or courier accounts yet.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $u)
                        <tr>
                            <td>{{ $u->name }}</td>
                            <td>{{ $u->email }}</td>
                            <td>{{ $u->phone ?? '—' }}</td>
                            <td>{{ $u->role->label() }}</td>
                            <td>{{ $u->is_active ? 'Active' : 'Inactive' }}</td>
                            <td>{{ $u->created_at?->format('Y-m-d') }}</td>
                            <td><a href="{{ route('owner.users.edit', $u) }}">Edit</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection
