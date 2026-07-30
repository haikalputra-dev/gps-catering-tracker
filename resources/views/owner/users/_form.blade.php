<label for="name">Name</label>
<input id="name" type="text" name="name" value="{{ old('name', $user->name ?? '') }}" required maxlength="100">

<label for="email">Email</label>
<input id="email" type="email" name="email" value="{{ old('email', $user->email ?? '') }}" required maxlength="255">

<label for="phone">Phone (optional)</label>
<input id="phone" type="text" name="phone" value="{{ old('phone', $user->phone ?? '') }}" maxlength="25">

<label for="role">Role</label>
<select id="role" name="role" required>
    @foreach($roles as $role)
        <option value="{{ $role->value }}" @selected(old('role', $user->role->value ?? '') === $role->value)>{{ $role->label() }}</option>
    @endforeach
</select>

<label for="password">Password{{ $mode === 'edit' ? ' (leave blank to keep current)' : '' }}</label>
<input id="password" type="password" name="password" autocomplete="new-password" @if($mode === 'create') required @endif minlength="8">

<label for="password_confirmation">Confirm Password</label>
<input id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password" @if($mode === 'create') required @endif minlength="8">

<label style="display: flex; align-items: center; gap: 8px; font-weight: 600; margin-top: 12px;">
    <input type="hidden" name="is_active" value="0">
    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', ($user->is_active ?? true)) == true)>
    Account active
</label>
