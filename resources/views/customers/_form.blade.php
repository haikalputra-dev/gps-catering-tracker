@php
    /** @var \App\Models\Customer $customer */
    /** @var array<string, mixed> $mapConfig */
    $nameVal = old('name', $customer->name ?? '');
    $phoneVal = old('phone', $customer->phone ?? '');
    $addressVal = old('address', $customer->address ?? '');
    $notesVal = old('notes', $customer->notes ?? '');
    $latVal = old('latitude', $customer->latitude);
    $lngVal = old('longitude', $customer->longitude);
    $isActiveVal = old('is_active', $customer->is_active ?? true);
    $isActiveChecked = (bool) filter_var($isActiveVal, FILTER_VALIDATE_BOOLEAN);
@endphp

<div class="space-y-5">
    <x-form-field
        name="name"
        id="customer-name"
        label="Name"
        :value="$nameVal"
        :required="true"
        maxlength="150" />

    <x-form-field
        name="phone"
        id="customer-phone"
        label="Phone"
        :value="$phoneVal"
        :required="true"
        maxlength="25"
        help="Indonesian format: +62812xxxxxxx or 0812xxxxxxx. Digits, spaces, hyphens and parentheses allowed." />

    <x-form-field
        name="address"
        id="customer-address"
        label="Address"
        type="textarea"
        :value="$addressVal"
        :required="true"
        rows="3" />

    <x-form-field
        name="notes"
        id="customer-notes"
        label="Delivery notes (optional)"
        type="textarea"
        :value="$notesVal"
        rows="3"
        maxlength="1000"
        help="Free-text hints such as gate code, floor number, or landmark. Do not store payment information." />

    <div>
        <span class="block text-sm font-medium text-slate-700 mb-1">Location</span>
        <p class="customer-map-instruction text-xs text-slate-500 mb-2">
            Click the map or drag the marker to select the customer delivery location.
        </p>
        <div
            id="customer-map"
            class="rounded-md border border-slate-300 overflow-hidden"
            data-default-latitude="{{ $mapConfig['defaultLatitude'] }}"
            data-default-longitude="{{ $mapConfig['defaultLongitude'] }}"
            data-default-zoom="{{ $mapConfig['defaultZoom'] }}"
            data-selection-zoom="{{ $mapConfig['selectionZoom'] }}"
            data-tile-url="{{ $mapConfig['tileUrl'] }}"
            data-tile-attribution="{{ $mapConfig['tileAttribution'] }}"
            data-tile-max-zoom="{{ $mapConfig['tileMaxZoom'] }}"
        ></div>
        <input id="customer-latitude" type="hidden" name="latitude" value="{{ $latVal }}">
        <input id="customer-longitude" type="hidden" name="longitude" value="{{ $lngVal }}">
        <div class="mt-2 text-xs text-slate-500">
            <span>Selected coordinate:</span>
            <span id="customer-coordinate-display" class="text-slate-700 font-medium">
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
                   class="rounded border-slate-300 text-red-600 focus:ring-red-500">
            <span>Customer is active</span>
        </label>
        <p class="mt-1 text-xs text-slate-500">
            Inactive customers are retained for historical reference and excluded from future delivery scheduling.
        </p>
    </div>

    <div class="pt-2 flex items-center gap-3">
        <x-button type="submit" icon="check">Save</x-button>
        <x-button :href="route('customers.index')" variant="secondary">Cancel</x-button>
    </div>
</div>
