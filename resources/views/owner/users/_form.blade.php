<div class="space-y-5">
    <x-form-field
        name="name"
        label="Name"
        :value="$user->name ?? ''"
        :required="true"
        maxlength="100" />

    <x-form-field
        name="email"
        label="Email"
        type="email"
        :value="$user->email ?? ''"
        :required="true"
        maxlength="255" />

    <x-form-field
        name="phone"
        label="Phone (optional)"
        :value="$user->phone ?? ''"
        maxlength="25" />

    <x-form-field name="role" label="Role" type="select" :required="true">
        @foreach($roles as $role)
            <option value="{{ $role->value }}" @selected(old('role', $user->role->value ?? '') === $role->value)>{{ $role->label() }}</option>
        @endforeach
    </x-form-field>

    <x-form-field
        name="password"
        :label="'Password' . ($mode === 'edit' ? ' (leave blank to keep current)' : '')"
        type="password"
        autocomplete="new-password"
        minlength="8"
        :required="$mode === 'create'" />

    <x-form-field
        name="password_confirmation"
        label="Confirm Password"
        type="password"
        autocomplete="new-password"
        minlength="8"
        :required="$mode === 'create'" />

    <div>
        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1"
                   @checked(old('is_active', ($user->is_active ?? true)) == true)
                   class="rounded border-slate-300 text-red-600 focus:ring-red-500">
            <span>Account active</span>
        </label>
    </div>
</div>
