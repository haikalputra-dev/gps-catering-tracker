@props([
    'fallback' => null,
    'label' => 'Back',
])

@php
    // Default the fallback to the landing page so a fresh tab that
    // lands on any deep URL still has a sane target when history is
    // empty. Callers can override with any URL (e.g. a section index).
    $fallback = $fallback ?: url('/');
@endphp

<a
    href="{{ $fallback }}"
    onclick="if (window.history.length > 1) { window.history.back(); return false; } return true;"
    {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 text-sm font-medium text-slate-600 hover:text-red-600 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 rounded-md px-2 py-1']) }}
>
    <x-heroicon-o-arrow-left class="w-4 h-4" aria-hidden="true" />
    {{ $label }}
</a>
