<div class="space-y-5">
    <x-form-field
        name="identifier"
        label="Identifier"
        :value="$device?->identifier"
        :required="true"
        maxlength="64"
        help="A short label such as the hardware serial (e.g. GT06-A1B2C3). Must be unique." />

    <x-form-field
        name="model"
        label="Model"
        :value="$device?->model"
        maxlength="80" />

    <x-form-field
        name="hardware_version"
        label="Hardware version"
        :value="$device?->hardware_version"
        maxlength="40" />

    <x-form-field
        name="notes"
        label="Notes"
        :value="$device?->notes"
        maxlength="500" />

    <x-form-field
        name="is_active"
        label="Status"
        type="select"
        help="Deactivating a device revokes its API token immediately and ends any live courier binding.">
        <option value="1" @selected(old('is_active', $device?->is_active ?? true))>Active</option>
        <option value="0" @selected(! old('is_active', $device?->is_active ?? true))>Inactive</option>
    </x-form-field>
</div>
