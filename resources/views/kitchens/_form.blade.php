@php
    /** @var \App\Models\Kitchen $kitchen */
    /** @var array<string, mixed> $mapConfig */
    $codeVal = old('code', $kitchen->code ?? '');
    $nameVal = old('name', $kitchen->name ?? '');
    $addressVal = old('address', $kitchen->address ?? '');
    $phoneVal = old('phone', $kitchen->phone ?? '');
    $latVal = old('latitude', $kitchen->latitude);
    $lngVal = old('longitude', $kitchen->longitude);
    $isActiveVal = old('is_active', $kitchen->is_active ?? true);
    $isActiveChecked = (bool) filter_var($isActiveVal, FILTER_VALIDATE_BOOLEAN);
@endphp

<div class="space-y-5">
    <x-form-field
        name="code"
        id="kitchen-code"
        label="Code"
        :value="$codeVal"
        :required="true"
        maxlength="30"
        help="Uppercase letters, digits and hyphens. Example: KITCHEN-01" />

    <x-form-field
        name="name"
        id="kitchen-name"
        label="Name"
        :value="$nameVal"
        :required="true"
        maxlength="150" />

    <x-form-field
        name="address"
        id="kitchen-address"
        label="Address"
        type="textarea"
        :value="$addressVal"
        :required="true"
        rows="3" />

    <x-form-field
        name="phone"
        id="kitchen-phone"
        label="Phone (optional)"
        :value="$phoneVal"
        maxlength="25" />

    <div>
        <span class="block text-sm font-medium text-slate-700 mb-1">Location</span>
        <p class="kitchen-map-instruction text-xs text-slate-500 mb-2">
            Click the map or drag the marker to select the kitchen location.
        </p>
        <div
            id="kitchen-map"
            class="rounded-md border border-slate-300 overflow-hidden"
            data-default-latitude="{{ $mapConfig['defaultLatitude'] }}"
            data-default-longitude="{{ $mapConfig['defaultLongitude'] }}"
            data-default-zoom="{{ $mapConfig['defaultZoom'] }}"
            data-selection-zoom="{{ $mapConfig['selectionZoom'] }}"
            data-tile-url="{{ $mapConfig['tileUrl'] }}"
            data-tile-attribution="{{ $mapConfig['tileAttribution'] }}"
            data-tile-max-zoom="{{ $mapConfig['tileMaxZoom'] }}"
        ></div>
        <input id="kitchen-latitude" type="hidden" name="latitude" value="{{ $latVal }}">
        <input id="kitchen-longitude" type="hidden" name="longitude" value="{{ $lngVal }}">
        <div class="mt-2 text-xs text-slate-500">
            <span>Selected coordinate:</span>
            <span id="kitchen-coordinate-display" class="text-slate-700 font-medium">
                @if($latVal !== null && $lngVal !== null && $latVal !== '' && $lngVal !== '')
                    {{ $latVal }}, {{ $lngVal }}
                @else
                    No coordinate selected.
                @endif
            </span>
        </div>
        @error('latitude')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
        @error('longitude')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked($isActiveChecked)
                   class="rounded border-slate-300 text-orange-600 focus:ring-orange-500">
            <span>Kitchen is active</span>
        </label>
        <p class="mt-1 text-xs text-slate-500">
            Inactive kitchens are retained for historical reference and excluded from future scheduling.
        </p>
    </div>

    <div class="pt-2 flex items-center gap-3">
        <x-button type="submit">Save</x-button>
        <x-button :href="route('kitchens.index')" variant="secondary">Cancel</x-button>
    </div>
</div>
