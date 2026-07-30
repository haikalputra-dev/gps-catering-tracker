<?php

declare(strict_types=1);

namespace App\Http\Requests\Kitchen;

use App\Domain\Kitchen\KitchenCode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreKitchenRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware `role:owner,staff` handles authorization.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');
        $normalizedCode = is_string($code) ? KitchenCode::normalize($code) : $code;

        $name = $this->input('name');
        $address = $this->input('address');
        $phone = $this->input('phone');

        $isActiveInput = $this->input('is_active');
        if ($isActiveInput === null || $isActiveInput === '') {
            $isActive = true;
        } else {
            $isActive = filter_var($isActiveInput, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive === null) {
                $isActive = true;
            }
        }

        $this->merge([
            'code' => $normalizedCode,
            'name' => is_string($name) ? trim($name) : $name,
            'address' => is_string($address) ? trim($address) : $address,
            'phone' => is_string($phone) ? (trim($phone) === '' ? null : trim($phone)) : $phone,
            'is_active' => $isActive,
        ]);
    }

    /**
     * @return array<string, array<int, string|\Illuminate\Contracts\Validation\Rule>>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9-]+$/',
                Rule::unique('kitchens', 'code'),
            ],
            'name' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:25', 'regex:/^[0-9+\-() ]+$/'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.regex' => 'The kitchen code may only contain uppercase letters, digits and hyphens.',
            'phone.regex' => 'The phone number may only contain digits, spaces, +, -, ( and ).',
        ];
    }
}
