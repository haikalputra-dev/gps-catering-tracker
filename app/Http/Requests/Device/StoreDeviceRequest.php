<?php

declare(strict_types=1);

namespace App\Http\Requests\Device;

use App\Domain\Identity\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user('web');

        return $user !== null && $user->role === UserRole::Owner;
    }

    protected function prepareForValidation(): void
    {
        $identifier = (string) $this->input('identifier', '');
        $model = $this->input('model');
        $hardware = $this->input('hardware_version');
        $notes = $this->input('notes');
        $isActive = $this->input('is_active');

        $this->merge([
            'identifier' => Str::of($identifier)->trim()->upper()->toString(),
            'model' => $model === null ? null : trim((string) $model),
            'hardware_version' => $hardware === null ? null : trim((string) $hardware),
            'notes' => $notes === null ? null : trim((string) $notes),
            'is_active' => $this->toBool($isActive, true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:64', 'unique:devices,identifier'],
            'model' => ['nullable', 'string', 'max:80'],
            'hardware_version' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:500'],
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
