@props([
    'fromName',
    'fromAddress',
    'fromPhone' => null,
    'toName',
    'toAddress',
    'toPhone' => null,
    'distance' => null,
])

<div class="grid grid-cols-1 md:grid-cols-[1fr_auto_1fr] gap-4 md:gap-6 items-stretch">
    {{-- From (kitchen) --}}
    <div class="p-5 bg-white border border-slate-200 rounded-lg">
        <div class="flex items-center gap-2 text-xs font-medium text-slate-500 uppercase tracking-wide mb-3">
            <x-heroicon-o-building-storefront class="w-4 h-4" />
            From
        </div>
        <p class="text-base font-semibold text-slate-900">{{ $fromName }}</p>
        <p class="text-sm text-slate-600 mt-1">{{ $fromAddress }}</p>
        @if($fromPhone)
            <a href="tel:{{ $fromPhone }}" class="mt-3 inline-flex items-center gap-1.5 text-sm text-red-600 hover:text-red-700 font-medium">
                <x-heroicon-o-phone class="w-4 h-4" />
                {{ $fromPhone }}
            </a>
        @endif
    </div>

    {{-- Connector with distance --}}
    <div class="flex md:flex-col items-center justify-center gap-2 py-2 md:py-0">
        <div class="hidden md:block w-px flex-1 bg-slate-200"></div>
        <div class="flex md:flex-col items-center gap-2">
            <x-heroicon-o-arrow-right class="w-5 h-5 text-slate-400 md:hidden" />
            <x-heroicon-o-arrow-down class="w-5 h-5 text-slate-400 hidden md:block" />
            @if($distance !== null)
                <span class="text-xs font-medium text-slate-600 whitespace-nowrap">
                    {{ number_format((float) $distance, 2) }} km
                </span>
            @endif
        </div>
        <div class="hidden md:block w-px flex-1 bg-slate-200"></div>
    </div>

    {{-- To (customer) --}}
    <div class="p-5 bg-white border border-slate-200 rounded-lg">
        <div class="flex items-center gap-2 text-xs font-medium text-slate-500 uppercase tracking-wide mb-3">
            <x-heroicon-o-map-pin class="w-4 h-4" />
            To
        </div>
        <p class="text-base font-semibold text-slate-900">{{ $toName }}</p>
        <p class="text-sm text-slate-600 mt-1">{{ $toAddress }}</p>
        @if($toPhone)
            <a href="tel:{{ $toPhone }}" class="mt-3 inline-flex items-center gap-1.5 text-sm text-red-600 hover:text-red-700 font-medium">
                <x-heroicon-o-phone class="w-4 h-4" />
                {{ $toPhone }}
            </a>
        @endif
    </div>
</div>
