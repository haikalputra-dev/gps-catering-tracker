@props([
    'label',
    'value',
    'sublabel' => null,
    'icon' => 'chart-bar',
    'href' => null,
])

@php
    $classes = 'block p-6 bg-white rounded-lg border border-slate-200 transition-shadow hover:shadow-md';
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
@else
    <div {{ $attributes->merge(['class' => $classes]) }}>
@endif

<div class="flex items-start justify-between">
    <div>
        <p class="text-sm font-medium text-slate-600">{{ $label }}</p>
        <p class="mt-2 text-3xl font-bold text-slate-900">{{ $value }}</p>
        @if($sublabel)
            <p class="mt-1 text-sm text-slate-500">{{ $sublabel }}</p>
        @endif
    </div>
    <div class="p-2 bg-red-50 rounded-lg text-red-600">
        @switch($icon)
            @case('kitchen')
                <x-heroicon-o-building-storefront class="w-6 h-6" />
                @break
            @case('customer')
                <x-heroicon-o-users class="w-6 h-6" />
                @break
            @case('truck')
                <x-heroicon-o-truck class="w-6 h-6" />
                @break
            @case('check')
                <x-heroicon-o-check-circle class="w-6 h-6" />
                @break
            @default
                <x-heroicon-o-chart-bar class="w-6 h-6" />
        @endswitch
    </div>
</div>

@if($href)
    </a>
@else
    </div>
@endif
