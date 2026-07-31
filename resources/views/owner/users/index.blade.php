@extends('layouts.app')

@section('title', 'User Accounts')

@section('content')
    <x-page-header
        title="User Accounts"
        subtitle="Only staff and courier accounts are listed. Owner accounts cannot be managed here.">
        <x-slot:actions>
            <x-button :href="route('owner.users.create')" icon="plus">
                Add Account
            </x-button>
        </x-slot:actions>
    </x-page-header>

    @if($users->isEmpty())
        <x-card>
            <x-empty-state
                icon="user-group"
                title="No staff or courier accounts yet."
                description="Create the first account to get your team on the platform."
                actionLabel="Add Account"
                :actionHref="route('owner.users.create')"
            />
        </x-card>
    @else
        <x-card padding="p-0">
            <x-table :headers="['Name', 'Email', 'Phone', 'Role', 'Status', 'Created', '']">
                @foreach($users as $u)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium text-slate-900 whitespace-nowrap">{{ $u->name }}</td>
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ $u->email }}</td>
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ $u->phone ?? '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <x-badge variant="neutral">{{ $u->role->label() }}</x-badge>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            @if($u->is_active)
                                <x-badge variant="success">Active</x-badge>
                            @else
                                <x-badge variant="danger">Inactive</x-badge>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-600 whitespace-nowrap">{{ $u->created_at?->format('Y-m-d') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <a href="{{ route('owner.users.edit', $u) }}"
                               class="text-red-600 hover:text-red-700 font-medium text-sm">
                                Edit
                            </a>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif
@endsection
