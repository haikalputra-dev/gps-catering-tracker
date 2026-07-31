@extends('layouts.app')

@section('title', 'Edit Account')

@section('content')
    <x-page-header
        :title="'Edit Account: ' . $user->name"
        subtitle="Update login details, role, or status.">
        <x-slot:actions>
            <x-button :href="route('owner.users.index')" variant="secondary">Back to Users</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <form method="POST" action="{{ route('owner.users.update', $user) }}" novalidate>
            @csrf
            @method('PUT')
            @include('owner.users._form', ['mode' => 'edit', 'user' => $user])
            <div class="mt-6 flex items-center gap-3">
                <x-button type="submit">Save Changes</x-button>
                <x-button :href="route('owner.users.index')" variant="secondary">Cancel</x-button>
            </div>
        </form>
    </x-card>
@endsection
