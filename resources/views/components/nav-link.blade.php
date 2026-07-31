@props([
    'href' => '#',
    'active' => false,
])

@php
    $base = 'inline-flex items-center gap-1.5 px-3 py-2 rounded-md text-sm font-medium transition-colors';
    $state = $active
        ? 'bg-orange-50 text-orange-700'
        : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100';
@endphp

<a href="{{ $href }}" {{ $attributes->merge(['class' => "{$base} {$state}"]) }} @if($active) aria-current="page" @endif>
    {{ $slot }}
</a>
