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
            <div class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-slate-300" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                </svg>
                <h3 class="mt-2 text-sm font-semibold text-slate-900">No staff or courier accounts yet.</h3>
                <p class="mt-1 text-sm text-slate-500">Create the first account to get your team on the platform.</p>
                <div class="mt-6">
                    <x-button :href="route('owner.users.create')">Add Account</x-button>
                </div>
            </div>
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
                               class="text-orange-600 hover:text-orange-700 font-medium text-sm">
                                Edit
                            </a>
                        </td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>
    @endif
@endsection
