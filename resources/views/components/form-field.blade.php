@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'required' => false,
    'help' => null,
    'placeholder' => null,
    'id' => null,
])

@php
    $inputId = $id ?? $name;
    $displayValue = old($name, $value);
    $hasError = $errors->has($name);
    $inputBase = 'block w-full rounded-md border shadow-sm px-3 py-2 text-sm text-slate-900 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-offset-0 transition-colors';
    $inputState = $hasError
        ? 'border-red-400 focus:border-red-500 focus:ring-red-500'
        : 'border-slate-300 focus:border-orange-500 focus:ring-orange-500';
    $inputClasses = $inputBase . ' ' . $inputState;
@endphp

<div>
    @if($label)
        <label for="{{ $inputId }}" class="block text-sm font-medium text-slate-700 mb-1">
            {{ $label }}
            @if($required)<span class="text-red-600" aria-hidden="true">*</span>@endif
        </label>
    @endif

    @if($type === 'textarea')
        <textarea
            name="{{ $name }}"
            id="{{ $inputId }}"
            @if($required) required @endif
            @if($placeholder) placeholder="{{ $placeholder }}" @endif
            {{ $attributes->merge(['class' => $inputClasses, 'rows' => 4]) }}
        >{{ $displayValue }}</textarea>
    @elseif($type === 'select')
        <select
            name="{{ $name }}"
            id="{{ $inputId }}"
            @if($required) required @endif
            {{ $attributes->merge(['class' => $inputClasses]) }}
        >{{ $slot }}</select>
    @else
        <input
            type="{{ $type }}"
            name="{{ $name }}"
            id="{{ $inputId }}"
            value="{{ $displayValue }}"
            @if($required) required @endif
            @if($placeholder) placeholder="{{ $placeholder }}" @endif
            {{ $attributes->merge(['class' => $inputClasses]) }}
        />
    @endif

    @if($help && !$hasError)
        <p class="mt-1 text-xs text-slate-500">{{ $help }}</p>
    @endif

    @error($name)
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
