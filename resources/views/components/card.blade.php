@props([
    'padding' => 'p-6',
])

<div {{ $attributes->merge(['class' => 'bg-white border border-slate-200 rounded-lg shadow-sm']) }}>
    @isset($header)
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between gap-4">
            <div class="min-w-0 flex-1">{{ $header }}</div>
            @isset($actions)
                <div class="flex-shrink-0">{{ $actions }}</div>
            @endisset
        </div>
    @endisset

    <div class="{{ $padding }}">{{ $slot }}</div>

    @isset($footer)
        <div class="px-6 py-4 border-t border-slate-200 bg-slate-50 rounded-b-lg">{{ $footer }}</div>
    @endisset
</div>
