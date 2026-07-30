<label for="identifier">Identifier</label>
<input type="text"
       id="identifier"
       name="identifier"
       value="{{ old('identifier', $device?->identifier) }}"
       maxlength="64"
       required>
<p class="placeholder">A short label such as the hardware serial (e.g. `GT06-A1B2C3`). Must be unique.</p>

<label for="model">Model</label>
<input type="text"
       id="model"
       name="model"
       value="{{ old('model', $device?->model) }}"
       maxlength="80">

<label for="hardware_version">Hardware version</label>
<input type="text"
       id="hardware_version"
       name="hardware_version"
       value="{{ old('hardware_version', $device?->hardware_version) }}"
       maxlength="40">

<label for="notes">Notes</label>
<input type="text"
       id="notes"
       name="notes"
       value="{{ old('notes', $device?->notes) }}"
       maxlength="500">

<label for="is_active">Status</label>
<select id="is_active" name="is_active">
    <option value="1" @selected(old('is_active', $device?->is_active ?? true))>Active</option>
    <option value="0" @selected(! old('is_active', $device?->is_active ?? true))>Inactive</option>
</select>
<p class="placeholder">Deactivating a device revokes its API token immediately and ends any live courier binding.</p>
