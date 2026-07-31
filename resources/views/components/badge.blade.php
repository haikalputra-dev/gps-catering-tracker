@props([
    'variant' => 'neutral',
])

@php
    $variants = [
        'neutral' => 'bg-slate-100 text-slate-700',
        'primary' => 'bg-red-100 text-red-800',
        'success' => 'bg-emerald-100 text-emerald-800',
        'info' => 'bg-sky-100 text-sky-800',
        'warning' => 'bg-amber-100 text-amber-800',
        'danger' => 'bg-red-100 text-red-800',
    ];
    $classes = $variants[$variant] ?? $variants['neutral'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium {$classes}"]) }}>
    {{ $slot }}
</span>
