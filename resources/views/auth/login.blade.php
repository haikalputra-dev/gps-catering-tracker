@extends('layouts.public')

@section('title', 'Log in')

@section('content')
    <div class="min-h-[70vh] flex items-center justify-center">
        <x-card class="w-full max-w-md" padding="p-8">
            <div class="text-center mb-6">
                <h1 class="text-2xl font-bold text-slate-900">Log in</h1>
                <p class="mt-1 text-sm text-slate-600">Access the GPS Catering Tracker dashboard</p>
            </div>
            <form method="POST" action="{{ route('login.attempt') }}" novalidate class="space-y-4">
                @csrf
                <x-form-field
                    name="email"
                    label="Email"
                    type="email"
                    :required="true"
                    autocomplete="username"
                    autofocus />
                <x-form-field
                    name="password"
                    label="Password"
                    type="password"
                    :required="true"
                    autocomplete="current-password" />
                <div class="pt-2">
                    <x-button type="submit" class="w-full justify-center">Log in</x-button>
                </div>
            </form>

            <div class="mt-6 pt-6 border-t border-slate-200 text-center">
                <a href="{{ url('/') }}"
                   class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 rounded-md px-2 py-1">
                    <x-heroicon-o-arrow-left class="w-4 h-4" aria-hidden="true" />
                    Back to home
                </a>
            </div>
        </x-card>
    </div>
@endsection
