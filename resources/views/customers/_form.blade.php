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

<div>
    <label for="customer-name">Name</label>
    <input id="customer-name" type="text" name="name" value="{{ $nameVal }}" maxlength="150" required>
</div>

<div>
    <label for="customer-phone">Phone</label>
    <input id="customer-phone" type="text" name="phone" value="{{ $phoneVal }}" maxlength="25" required>
    <small class="placeholder">Indonesian format: +62812xxxxxxx or 0812xxxxxxx. Digits, spaces, hyphens and parentheses allowed.</small>
</div>

<div>
    <label for="customer-address">Address</label>
    <textarea id="customer-address" name="address" rows="3" required>{{ $addressVal }}</textarea>
</div>

<div>
    <label for="customer-notes">Delivery notes (optional)</label>
    <textarea id="customer-notes" name="notes" rows="3" maxlength="1000">{{ $notesVal }}</textarea>
    <small class="placeholder">Free-text hints such as gate code, floor number, or landmark. Do not store payment information.</small>
</div>

<div>
    <label>Location</label>
    <p class="customer-map-instruction">
        Click the map or drag the marker to select the customer delivery location.
    </p>
    <div
        id="customer-map"
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
    <div>
        <span class="placeholder">Selected coordinate:</span>
        <span id="customer-coordinate-display">
            @if($latVal !== null && $lngVal !== null && $latVal !== '' && $lngVal !== '')
                {{ $latVal }}, {{ $lngVal }}
            @else
                No coordinate selected.
            @endif
        </span>
    </div>
</div>

<div style="margin-top:12px;">
    <label style="display:flex;align-items:center;gap:8px;font-weight:600;">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1" @checked($isActiveChecked)>
        <span>Customer is active</span>
    </label>
    <small class="placeholder">Inactive customers are retained for historical reference and excluded from future delivery scheduling.</small>
</div>

<div style="margin-top:16px;display:flex;gap:12px;align-items:center;">
    <button type="submit">Save</button>
    <a href="{{ route('customers.index') }}" class="btn secondary">Cancel</a>
</div>
