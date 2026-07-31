@extends('layouts.app')

@section('title', 'Staff Dashboard')

@section('content')
    <x-page-header
        title="Staff Dashboard"
        :subtitle="'Welcome back, ' . auth()->user()->name . '.'" />

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <a href="{{ route('deliveries.create') }}" class="group">
            <x-card class="h-full hover:border-orange-400 hover:shadow-md transition">
                <div class="flex items-start gap-3">
                    <div class="rounded-md bg-orange-50 p-3 group-hover:bg-orange-100 transition">
                        <svg class="w-6 h-6 text-orange-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">New Delivery</h3>
                        <p class="mt-1 text-sm text-slate-600">Create a new delivery draft.</p>
                    </div>
                </div>
            </x-card>
        </a>
        <a href="{{ route('deliveries.index') }}" class="group">
            <x-card class="h-full hover:border-orange-400 hover:shadow-md transition">
                <div class="flex items-start gap-3">
                    <div class="rounded-md bg-sky-50 p-3 group-hover:bg-sky-100 transition">
                        <svg class="w-6 h-6 text-sky-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177V16.5m0 0h-3M14.25 7.573a2.25 2.25 0 0 1 4.5 0" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Deliveries</h3>
                        <p class="mt-1 text-sm text-slate-600">Track and manage all deliveries.</p>
                    </div>
                </div>
            </x-card>
        </a>
        <a href="{{ route('kitchens.index') }}" class="group">
            <x-card class="h-full hover:border-orange-400 hover:shadow-md transition">
                <div class="flex items-start gap-3">
                    <div class="rounded-md bg-emerald-50 p-3 group-hover:bg-emerald-100 transition">
                        <svg class="w-6 h-6 text-emerald-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955a.75.75 0 0 1 1.06 0L21.219 12M4.5 9.75v10.125A1.125 1.125 0 0 0 5.625 21H9.75v-4.875a1.125 1.125 0 0 1 1.125-1.125h2.25a1.125 1.125 0 0 1 1.125 1.125V21h4.125a1.125 1.125 0 0 0 1.125-1.125V9.75" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Kitchens</h3>
                        <p class="mt-1 text-sm text-slate-600">Manage kitchen locations.</p>
                    </div>
                </div>
            </x-card>
        </a>
        <a href="{{ route('customers.index') }}" class="group">
            <x-card class="h-full hover:border-orange-400 hover:shadow-md transition">
                <div class="flex items-start gap-3">
                    <div class="rounded-md bg-amber-50 p-3 group-hover:bg-amber-100 transition">
                        <svg class="w-6 h-6 text-amber-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-lg font-semibold text-slate-900">Customers</h3>
                        <p class="mt-1 text-sm text-slate-600">Manage customer profiles.</p>
                    </div>
                </div>
            </x-card>
        </a>
    </div>
@endsection
