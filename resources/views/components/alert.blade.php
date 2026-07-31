@props([
    'variant' => 'info',
    'title' => null,
    'dismissible' => false,
])

@php
    $variants = [
        'success' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
        'info' => 'bg-sky-50 border-sky-200 text-sky-800',
        'warning' => 'bg-amber-50 border-amber-200 text-amber-800',
        'danger' => 'bg-red-50 border-red-200 text-red-800',
        'error' => 'bg-red-50 border-red-200 text-red-800',
    ];
    $classes = $variants[$variant] ?? $variants['info'];
@endphp

<div {{ $attributes->merge(['class' => "border rounded-md px-4 py-3 text-sm {$classes}"]) }} role="alert">
    @if($title)
        <p class="font-semibold mb-1">{{ $title }}</p>
    @endif
    <div class="leading-relaxed">{{ $slot }}</div>
</div>
