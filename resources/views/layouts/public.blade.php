<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Delivery Tracking')</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full font-sans antialiased text-slate-900 bg-slate-50 flex flex-col">
    <header class="bg-white border-b border-slate-200 py-5">
        <div class="max-w-3xl mx-auto px-4 flex items-center justify-center gap-2">
            <svg class="w-6 h-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z"/>
            </svg>
            <h1 class="text-lg font-bold text-slate-900">GPS Catering Tracker</h1>
        </div>
    </header>

    @include('partials._flash_toasts')

    <main class="flex-1 w-full max-w-3xl mx-auto px-4 sm:px-6 py-8">
        <div class="space-y-6">
            @yield('content')
        </div>
    </main>

    <footer class="py-6 text-center text-xs text-slate-500">
        &copy; {{ date('Y') }} GPS Catering Tracker
    </footer>
</body>
</html>
