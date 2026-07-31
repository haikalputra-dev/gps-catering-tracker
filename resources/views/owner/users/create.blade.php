@extends('layouts.app')

@section('title', 'Add Account')

@section('content')
    <x-page-header
        title="Add Account"
        subtitle="Create a new staff or courier login. Owner accounts cannot be managed here.">
        <x-slot:actions>
            <x-button :href="route('owner.users.index')" variant="secondary">Back to Users</x-button>
        </x-slot:actions>
    </x-page-header>

    <x-card>
        <form method="POST" action="{{ route('owner.users.store') }}" novalidate>
            @csrf
            @include('owner.users._form', ['mode' => 'create', 'user' => null])
            <div class="mt-6 flex items-center gap-3">
                <x-button type="submit" icon="check">Create Account</x-button>
                <x-button :href="route('owner.users.index')" variant="secondary">Cancel</x-button>
            </div>
        </form>
    </x-card>
@endsection
