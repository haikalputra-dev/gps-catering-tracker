<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'GPS Catering Tracker') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full font-sans antialiased text-slate-900 bg-slate-50 flex items-center justify-center px-4">
    <div class="max-w-lg text-center">
        <div class="inline-flex items-center gap-2 mb-6">
            <svg class="w-8 h-8 text-orange-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>
            </svg>
            <span class="text-2xl font-bold text-slate-900">GPS Catering Tracker</span>
        </div>
        <h1 class="text-3xl font-bold tracking-tight text-slate-900 sm:text-4xl">Real-time catering delivery tracking</h1>
        <p class="mt-4 text-base text-slate-600">Coordinate kitchens, couriers, and customers on one live map.</p>
        <div class="mt-8 flex items-center justify-center gap-3">
            @auth
                <a href="{{ url('/dashboard') }}" class="inline-flex items-center justify-center gap-2 rounded-md bg-orange-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-orange-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-offset-2">
                    Go to Dashboard
                </a>
            @else
                <a href="{{ route('login') }}" class="inline-flex items-center justify-center gap-2 rounded-md bg-orange-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-orange-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-orange-500 focus-visible:ring-offset-2">
                    Log in
                </a>
            @endauth
        </div>
    </div>
</body>
</html>
