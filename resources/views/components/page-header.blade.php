@props([
    'title',
    'subtitle' => null,
])

<div {{ $attributes->merge(['class' => 'flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 pb-4 border-b border-slate-200']) }}>
    <div class="min-w-0">
        @isset($breadcrumbs)
            <nav class="mb-2 text-xs text-slate-500" aria-label="Breadcrumb">
                {{ $breadcrumbs }}
            </nav>
        @endisset
        <h1 class="text-2xl font-bold text-slate-900 truncate">{{ $title }}</h1>
        @if($subtitle)
            <p class="mt-1 text-sm text-slate-600">{{ $subtitle }}</p>
        @endif
    </div>
    @isset($actions)
        <div class="flex flex-wrap items-center gap-2 sm:flex-shrink-0">
            {{ $actions }}
        </div>
    @endisset
</div>
