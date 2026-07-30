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

<div>
    <label for="kitchen-code">Code</label>
    <input id="kitchen-code" type="text" name="code" value="{{ $codeVal }}" maxlength="30" required>
    <small class="placeholder">Uppercase letters, digits and hyphens. Example: KITCHEN-01</small>
</div>

<div>
    <label for="kitchen-name">Name</label>
    <input id="kitchen-name" type="text" name="name" value="{{ $nameVal }}" maxlength="150" required>
</div>

<div>
    <label for="kitchen-address">Address</label>
    <textarea id="kitchen-address" name="address" rows="3" style="width:100%;padding:8px;border:1px solid #d1d5db;border-radius:4px;" required>{{ $addressVal }}</textarea>
</div>

<div>
    <label for="kitchen-phone">Phone (optional)</label>
    <input id="kitchen-phone" type="text" name="phone" value="{{ $phoneVal }}" maxlength="25">
</div>

<div>
    <label>Location</label>
    <p class="kitchen-map-instruction">
        Click the map or drag the marker to select the kitchen location.
    </p>
    <div
        id="kitchen-map"
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
    <div>
        <span class="placeholder">Selected coordinate:</span>
        <span id="kitchen-coordinate-display">
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
        <span>Kitchen is active</span>
    </label>
    <small class="placeholder">Inactive kitchens are retained for historical reference and excluded from future scheduling.</small>
</div>

<div style="margin-top:16px;display:flex;gap:12px;align-items:center;">
    <button type="submit">Save</button>
    <a href="{{ route('kitchens.index') }}" class="btn secondary">Cancel</a>
</div>
