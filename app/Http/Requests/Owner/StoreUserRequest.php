<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use App\Domain\Identity\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user('web');

        return $user !== null && $user->role === UserRole::Owner;
    }

    protected function prepareForValidation(): void
    {
        $email = (string) $this->input('email', '');
        $phone = $this->input('phone');
        $isActive = $this->input('is_active');

        $this->merge([
            'email' => Str::of($email)->trim()->lower()->toString(),
            'phone' => $phone === null ? null : trim((string) $phone),
            'is_active' => $this->toBool($isActive, true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:25', 'regex:/^[0-9+\-() ]+$/'],
            'role' => ['required', 'string', Rule::in(UserRole::manageableValues())],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    private function toBool(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }
}
