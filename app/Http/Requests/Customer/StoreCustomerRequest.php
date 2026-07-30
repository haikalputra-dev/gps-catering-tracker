<?php

declare(strict_types=1);

namespace App\Http\Requests\Customer;

use App\Domain\Customer\CustomerPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route middleware `role:owner,staff` enforces authorization.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');
        $address = $this->input('address');
        $notes = $this->input('notes');
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
            'name' => is_string($name) ? trim($name) : $name,
            'phone' => is_string($phone) ? CustomerPhone::normalize($phone) : $phone,
            'address' => is_string($address) ? trim($address) : $address,
            'notes' => is_string($notes)
                ? (trim($notes) === '' ? null : trim($notes))
                : $notes,
            'is_active' => $isActive,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => [
                'required',
                'string',
                'max:25',
                'regex:/^\+?[0-9]{'.CustomerPhone::MIN_DIGITS.','.CustomerPhone::MAX_DIGITS.'}$/',
                Rule::unique('customers', 'phone'),
            ],
            'address' => ['required', 'string', 'max:1000'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'The phone number must contain '
                .CustomerPhone::MIN_DIGITS.' to '.CustomerPhone::MAX_DIGITS
                .' digits and may start with a plus sign.',
        ];
    }
}
