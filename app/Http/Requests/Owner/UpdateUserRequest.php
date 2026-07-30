<?php

declare(strict_types=1);

namespace App\Http\Requests\Owner;

use App\Domain\Identity\UserRole;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
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
        $password = $this->input('password');

        $this->merge([
            'email' => Str::of($email)->trim()->lower()->toString(),
            'phone' => $phone === null ? null : trim((string) $phone),
            'is_active' => $this->toBool($isActive, false),
            'password' => $password === '' ? null : $password,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User|null $target */
        $target = $this->route('user');
        $ignoreId = $target?->getKey();

        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required', 'email:rfc', 'max:255',
                Rule::unique('users', 'email')->ignore($ignoreId),
            ],
            'phone' => ['nullable', 'string', 'max:25', 'regex:/^[0-9+\-() ]+$/'],
            'role' => ['required', 'string', Rule::in(UserRole::manageableValues())],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
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
