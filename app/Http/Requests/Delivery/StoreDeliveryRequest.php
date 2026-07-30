<?php

declare(strict_types=1);

namespace App\Http\Requests\Delivery;

use App\Models\Customer;
use App\Models\Kitchen;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates payload for creating a new delivery draft.
 *
 * Route middleware `role:owner,staff` handles authorization; the request
 * only normalises input and enforces field-level rules. A newly created
 * delivery is always in `draft` status and never has a receipt number.
 */
class StoreDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $notes = $this->input('notes');
        $scheduledAt = $this->input('scheduled_at');

        $this->merge([
            'notes' => is_string($notes)
                ? (trim($notes) === '' ? null : trim($notes))
                : $notes,
            'scheduled_at' => is_string($scheduledAt) && trim($scheduledAt) === ''
                ? null
                : $scheduledAt,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'kitchen_id' => [
                'required',
                'integer',
                Rule::exists(Kitchen::class, 'id')->where(
                    fn ($query) => $query->where('is_active', true),
                ),
            ],
            'customer_id' => [
                'required',
                'integer',
                Rule::exists(Customer::class, 'id')->where(
                    fn ($query) => $query->where('is_active', true),
                ),
            ],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kitchen_id.exists' => 'The selected kitchen must be active.',
            'customer_id.exists' => 'The selected customer must be active.',
            'scheduled_at.after' => 'The scheduled time must be in the future.',
        ];
    }
}
