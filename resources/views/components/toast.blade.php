@props([
    'variant' => 'success',
    'title' => null,
])

@php
    $variants = [
        'success' => [
            'wrap' => 'bg-white border-emerald-200 shadow-lg',
            'icon' => 'check-circle',
            'iconColor' => 'text-emerald-600',
        ],
        'info' => [
            'wrap' => 'bg-white border-sky-200 shadow-lg',
            'icon' => 'information-circle',
            'iconColor' => 'text-sky-600',
        ],
        'warning' => [
            'wrap' => 'bg-white border-amber-200 shadow-lg',
            'icon' => 'exclamation-triangle',
            'iconColor' => 'text-amber-600',
        ],
        'danger' => [
            'wrap' => 'bg-white border-red-200 shadow-lg',
            'icon' => 'x-circle',
            'iconColor' => 'text-red-600',
        ],
    ];
    $cfg = $variants[$variant] ?? $variants['success'];
    $liveRole = $variant === 'danger' ? 'alert' : 'status';
@endphp

<div
    data-toast
    data-toast-variant="{{ $variant }}"
    role="{{ $liveRole }}"
    aria-live="{{ $variant === 'danger' ? 'assertive' : 'polite' }}"
    {{ $attributes->merge(['class' => 'flex items-start gap-3 p-4 rounded-lg border ' . $cfg['wrap']]) }}
>
    <x-dynamic-component :component="'heroicon-o-' . $cfg['icon']" class="w-5 h-5 mt-0.5 shrink-0 {{ $cfg['iconColor'] }}" />
    <div class="flex-1 min-w-0 text-sm">
        @if($title)
            <p class="font-semibold text-slate-900">{{ $title }}</p>
            <div class="mt-1 text-slate-700">
                {{ $slot }}
            </div>
        @else
            <div class="text-slate-800">
                {{ $slot }}
            </div>
        @endif
    </div>
    <button
        type="button"
        data-toast-close
        aria-label="Dismiss notification"
        class="shrink-0 -m-1 p-1 rounded-md text-slate-400 hover:text-slate-600 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-slate-300"
    >
        <x-heroicon-o-x-mark class="w-4 h-4" />
    </button>
</div>
