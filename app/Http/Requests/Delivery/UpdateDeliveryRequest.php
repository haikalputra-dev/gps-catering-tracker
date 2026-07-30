<?php

declare(strict_types=1);

namespace App\Http\Requests\Delivery;

use App\Domain\Delivery\DeliveryStatus;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Kitchen;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * Validates payload for editing an existing delivery.
 *
 * Only drafts are editable (AR-25). Non-draft deliveries are rejected with
 * a 403 before validation even runs.
 */
class UpdateDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $delivery = $this->route('delivery');

        if (! $delivery instanceof Delivery) {
            return false;
        }

        return $delivery->status === DeliveryStatus::Draft;
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            redirect()
                ->route('deliveries.show', $this->route('delivery'))
                ->withErrors([
                    'status' => 'Only draft deliveries can be edited.',
                ]),
        );
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
