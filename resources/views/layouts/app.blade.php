<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'GPS Catering Tracker')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full font-sans antialiased text-slate-900 bg-slate-50">
    @auth
        @php
            $user = auth()->user();
            $isOwner = $user->isOwner();
            $isStaff = $user->isStaff();
            $isCourier = $user->isCourier();
            $isOffice = $isOwner || $isStaff;
        @endphp
        <nav class="bg-white border-b border-slate-200 shadow-sm" x-data>
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between h-16">
                    <div class="flex items-center gap-8">
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-2">
                            <svg class="w-6 h-6 text-orange-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>
                            </svg>
                            <span class="text-lg font-bold text-slate-900 hidden sm:inline">GPS Catering Tracker</span>
                            <span class="text-lg font-bold text-slate-900 sm:hidden">GPS CT</span>
                        </a>
                        <div class="hidden md:flex md:items-center md:gap-1">
                            @if($isCourier)
                                <x-nav-link :href="route('courier.dashboard')" :active="request()->routeIs('courier.dashboard')">
                                    <x-heroicon-o-home class="w-5 h-5" />
                                    My Dashboard
                                </x-nav-link>
                            @endif
                            @if($isOffice)
                                <x-nav-link :href="route('deliveries.index')" :active="request()->routeIs('deliveries.*')">
                                    <x-heroicon-o-truck class="w-5 h-5" />
                                    Deliveries
                                </x-nav-link>
                                <x-nav-link :href="route('kitchens.index')" :active="request()->routeIs('kitchens.*')">
                                    <x-heroicon-o-building-storefront class="w-5 h-5" />
                                    Kitchens
                                </x-nav-link>
                                <x-nav-link :href="route('customers.index')" :active="request()->routeIs('customers.*')">
                                    <x-heroicon-o-users class="w-5 h-5" />
                                    Customers
                                </x-nav-link>
                            @endif
                            @if($isOwner)
                                <x-nav-link :href="route('owner.users.index')" :active="request()->routeIs('owner.users.*')">
                                    <x-heroicon-o-user-group class="w-5 h-5" />
                                    User Accounts
                                </x-nav-link>
                                <x-nav-link :href="route('devices.index')" :active="request()->routeIs('devices.*')">
                                    <x-heroicon-o-device-phone-mobile class="w-5 h-5" />
                                    Devices
                                </x-nav-link>
                            @endif
                        </div>
                    </div>
                    <div class="hidden md:flex md:items-center md:gap-3">
                        <div class="flex flex-col items-end leading-tight">
                            <span class="text-sm font-medium text-slate-900">{{ $user->name }}</span>
                            <span class="text-xs text-slate-500">{{ $user->role->label() }}</span>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <x-button type="submit" variant="ghost">
                                <x-heroicon-o-arrow-right-on-rectangle class="w-4 h-4" />
                                Log out
                            </x-button>
                        </form>
                    </div>
                    <div class="md:hidden flex items-center">
                        <button type="button"
                                onclick="document.getElementById('mobile-menu').classList.toggle('hidden')"
                                class="inline-flex items-center justify-center p-2 rounded-md text-slate-600 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-orange-500"
                                aria-controls="mobile-menu"
                                aria-expanded="false">
                            <span class="sr-only">Open main menu</span>
                            <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
            <div id="mobile-menu" class="md:hidden hidden border-t border-slate-200">
                <div class="px-2 pt-2 pb-3 space-y-1">
                    @if($isCourier)
                        <a href="{{ route('courier.dashboard') }}" class="flex items-center gap-2 px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-100">
                            <x-heroicon-o-home class="w-5 h-5" />
                            My Dashboard
                        </a>
                    @endif
                    @if($isOffice)
                        <a href="{{ route('deliveries.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-100">
                            <x-heroicon-o-truck class="w-5 h-5" />
                            Deliveries
                        </a>
                        <a href="{{ route('kitchens.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-100">
                            <x-heroicon-o-building-storefront class="w-5 h-5" />
                            Kitchens
                        </a>
                        <a href="{{ route('customers.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-100">
                            <x-heroicon-o-users class="w-5 h-5" />
                            Customers
                        </a>
                    @endif
                    @if($isOwner)
                        <a href="{{ route('owner.users.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-100">
                            <x-heroicon-o-user-group class="w-5 h-5" />
                            User Accounts
                        </a>
                        <a href="{{ route('devices.index') }}" class="flex items-center gap-2 px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-100">
                            <x-heroicon-o-device-phone-mobile class="w-5 h-5" />
                            Devices
                        </a>
                    @endif
                </div>
                <div class="pt-3 pb-3 border-t border-slate-200">
                    <div class="px-4 mb-2">
                        <div class="text-sm font-medium text-slate-900">{{ $user->name }}</div>
                        <div class="text-xs text-slate-500">{{ $user->role->label() }}</div>
                    </div>
                    <div class="px-2">
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="w-full text-left block px-3 py-2 rounded-md text-base font-medium text-slate-700 hover:bg-slate-100">Log out</button>
                        </form>
                    </div>
                </div>
            </div>
        </nav>
    @endauth

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        @if(session('status'))
            <div class="mb-6">
                <x-alert variant="success">{{ session('status') }}</x-alert>
            </div>
        @endif
        @if(session('success'))
            <div class="mb-6">
                <x-alert variant="success">{{ session('success') }}</x-alert>
            </div>
        @endif
        @if(session('info'))
            <div class="mb-6">
                <x-alert variant="info">{{ session('info') }}</x-alert>
            </div>
        @endif
        @if(session('warning'))
            <div class="mb-6">
                <x-alert variant="warning">{{ session('warning') }}</x-alert>
            </div>
        @endif
        @if(session('error'))
            <div class="mb-6">
                <x-alert variant="danger">{{ session('error') }}</x-alert>
            </div>
        @endif
        @if($errors->any())
            <div class="mb-6">
                <x-alert variant="danger" title="Please fix the following:">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </x-alert>
            </div>
        @endif
        <div class="space-y-6">
            @yield('content')
        </div>
    </main>
</body>
</html>
