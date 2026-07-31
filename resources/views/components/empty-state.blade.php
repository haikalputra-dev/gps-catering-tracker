@props([
    'icon' => 'inbox',
    'title',
    'description' => null,
    'actionLabel' => null,
    'actionHref' => null,
    'actionIcon' => 'plus',
])

<div {{ $attributes->merge(['class' => 'text-center py-12 px-6']) }}>
    <div class="inline-flex items-center justify-center w-16 h-16 bg-slate-100 rounded-full">
        <x-dynamic-component :component="'heroicon-o-' . $icon" class="w-8 h-8 text-slate-400" />
    </div>
    <h3 class="mt-4 text-base font-semibold text-slate-900">{{ $title }}</h3>
    @if($description)
        <p class="mt-2 text-sm text-slate-600 max-w-md mx-auto">{{ $description }}</p>
    @endif
    @if($actionLabel && $actionHref)
        <div class="mt-6">
            <x-button :href="$actionHref" :icon="$actionIcon">{{ $actionLabel }}</x-button>
        </div>
    @endif
    {{ $slot }}
</div>
