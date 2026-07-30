<?php

declare(strict_types=1);

namespace App\Http\Requests\Delivery;

use App\Domain\Delivery\DeliveryStatus;
use App\Models\Delivery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Guards the POST /deliveries/{delivery}/cancel endpoint.
 *
 * Cancellation is only allowed from `draft` or `scheduled`. Terminal
 * deliveries (delivered, cancelled) are rejected here so the service
 * never sees them.
 */
class CancelDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $delivery = $this->route('delivery');

        if (! $delivery instanceof Delivery) {
            return false;
        }

        return $delivery->status->canTransitionTo(DeliveryStatus::Cancelled);
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            redirect()
                ->route('deliveries.show', $this->route('delivery'))
                ->withErrors([
                    'status' => 'This delivery cannot be cancelled from its current status.',
                ]),
        );
    }

    protected function prepareForValidation(): void
    {
        $reason = $this->input('cancellation_reason');

        $this->merge([
            'cancellation_reason' => is_string($reason) ? trim($reason) : $reason,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'A cancellation reason is required.',
            'cancellation_reason.min' => 'The cancellation reason must be at least 3 characters.',
            'cancellation_reason.max' => 'The cancellation reason must not exceed 255 characters.',
        ];
    }
}
