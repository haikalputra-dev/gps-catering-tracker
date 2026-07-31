{{--
    Public landing page (AR-60). Chrome-free, standalone view served
    at `/` for guests. Authenticated users are redirected to
    `/dashboard` before this template is rendered (see routes/web.php).

    Do NOT wrap this in layouts/app.blade.php — the landing has its
    own sticky nav and footer.
--}}
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>GPS Catering Tracker</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white text-slate-900 antialiased font-sans min-h-full flex flex-col">
    {{-- Sticky top nav --}}
    <nav class="bg-white border-b border-slate-200 sticky top-0 z-10">
        <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between gap-3">
            <a href="{{ route('welcome') }}" class="flex items-center gap-2">
                <x-heroicon-o-map-pin class="w-6 h-6 text-red-600" />
                <span class="text-lg font-bold text-slate-900 hidden sm:inline">GPS Catering Tracker</span>
                <span class="text-lg font-bold text-slate-900 sm:hidden">GPS CT</span>
            </a>
            <div class="flex items-center gap-2 sm:gap-3">
                <a href="{{ route('tracking.form') }}"
                   class="inline-flex items-center justify-center rounded-md border border-red-600 text-red-600 hover:bg-red-50 px-3 sm:px-4 py-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                    Track a delivery
                </a>
                <a href="{{ route('login') }}"
                   class="inline-flex items-center justify-center rounded-md bg-red-600 text-white hover:bg-red-700 px-3 sm:px-4 py-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                    Staff log in
                </a>
            </div>
        </div>
    </nav>

    <main class="flex-1">
        {{-- Hero --}}
        <section class="py-16 sm:py-24 text-center max-w-3xl mx-auto px-6">
            <h1 class="text-4xl sm:text-5xl font-bold tracking-tight text-slate-900">
                Real-time catering delivery tracking
            </h1>
            <p class="mt-4 text-lg text-slate-600">
                Coordinate kitchens, couriers, and customers on one live map.
            </p>

            <div class="mt-8 flex flex-col sm:flex-row justify-center gap-3">
                @auth
                    <a href="{{ url('/dashboard') }}"
                       class="inline-flex items-center justify-center gap-2 rounded-md bg-red-600 text-white hover:bg-red-700 px-6 py-3 text-base font-semibold focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                        Go to Dashboard
                    </a>
                @else
                    <a href="{{ route('tracking.form') }}"
                       class="inline-flex items-center justify-center gap-2 rounded-md border border-red-600 text-red-600 hover:bg-red-50 px-6 py-3 text-base font-semibold focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                        Track a delivery
                    </a>
                    <a href="{{ route('login') }}"
                       class="inline-flex items-center justify-center gap-2 rounded-md bg-red-600 text-white hover:bg-red-700 px-6 py-3 text-base font-semibold focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                        Staff log in
                    </a>
                @endauth
            </div>
        </section>

        {{-- Features grid --}}
        <section class="py-12 bg-slate-50">
            <div class="max-w-6xl mx-auto px-6 grid gap-6 md:grid-cols-3">
                <div class="bg-white rounded-lg border border-slate-200 p-6">
                    <div class="w-10 h-10 rounded-lg bg-red-50 text-red-600 flex items-center justify-center">
                        <x-heroicon-o-map-pin class="w-6 h-6" />
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-slate-900">Live GPS Tracking</h3>
                    <p class="mt-2 text-sm text-slate-600">
                        Follow every courier on a live map with second-by-second updates.
                    </p>
                </div>
                <div class="bg-white rounded-lg border border-slate-200 p-6">
                    <div class="w-10 h-10 rounded-lg bg-red-50 text-red-600 flex items-center justify-center">
                        <x-heroicon-o-banknotes class="w-6 h-6" />
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-slate-900">Automatic Pricing</h3>
                    <p class="mt-2 text-sm text-slate-600">
                        Distance-based fees are calculated the moment an order is created.
                    </p>
                </div>
                <div class="bg-white rounded-lg border border-slate-200 p-6">
                    <div class="w-10 h-10 rounded-lg bg-red-50 text-red-600 flex items-center justify-center">
                        <x-heroicon-o-users class="w-6 h-6" />
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-slate-900">Kitchen + Courier Ops</h3>
                    <p class="mt-2 text-sm text-slate-600">
                        Manage kitchens, couriers, and customers from one dashboard.
                    </p>
                </div>
            </div>
        </section>

        {{-- How it works --}}
        <section class="py-12 bg-white">
            <div class="max-w-6xl mx-auto px-6">
                <h2 class="text-2xl font-bold text-center mb-8 text-slate-900">How it works</h2>
                <div class="grid md:grid-cols-3 gap-6">
                    <div class="bg-white rounded-lg border border-slate-200 p-6">
                        <div class="w-8 h-8 rounded-full bg-red-600 text-white flex items-center justify-center font-bold">
                            1
                        </div>
                        <h3 class="mt-4 text-lg font-semibold text-slate-900">Kitchen schedules delivery</h3>
                        <p class="mt-2 text-sm text-slate-600">
                            Staff pick a kitchen, a customer, and a time. A receipt is issued automatically.
                        </p>
                    </div>
                    <div class="bg-white rounded-lg border border-slate-200 p-6">
                        <div class="w-8 h-8 rounded-full bg-red-600 text-white flex items-center justify-center font-bold">
                            2
                        </div>
                        <h3 class="mt-4 text-lg font-semibold text-slate-900">Courier is dispatched with GPS device</h3>
                        <p class="mt-2 text-sm text-slate-600">
                            The assigned courier taps Start Delivery and their tracker begins streaming position.
                        </p>
                    </div>
                    <div class="bg-white rounded-lg border border-slate-200 p-6">
                        <div class="w-8 h-8 rounded-full bg-red-600 text-white flex items-center justify-center font-bold">
                            3
                        </div>
                        <h3 class="mt-4 text-lg font-semibold text-slate-900">Customer tracks in real time</h3>
                        <p class="mt-2 text-sm text-slate-600">
                            The customer looks up their receipt and watches the courier approach on a live map.
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <footer class="bg-slate-50 border-t border-slate-200 py-8">
        <div class="max-w-6xl mx-auto px-6 text-center text-xs text-slate-500">
            &copy; {{ date('Y') }} GPS Catering Tracker &middot;
            <a href="{{ route('tracking.form') }}" class="hover:text-red-600">Track a delivery</a>
        </div>
    </footer>
</body>
</html>
