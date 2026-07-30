<?php

declare(strict_types=1);

namespace App\Http\Requests\Delivery;

use App\Domain\Delivery\DeliveryStatus;
use App\Domain\Identity\UserRole;
use App\Models\Delivery;
use App\Models\User;
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

        if (! $delivery->status->canTransitionTo(DeliveryStatus::Cancelled)) {
            return false;
        }

        $actor = $this->user();

        if (! $actor instanceof User) {
            return false;
        }

        $role = $actor->role;

        // Owner and staff may cancel any non-terminal delivery.
        if ($role === UserRole::Owner || $role === UserRole::Staff) {
            return true;
        }

        // A courier may cancel only their own in_transit delivery (AR-38 revised).
        if ($role === UserRole::Courier) {
            $isAssigned = $delivery->courier_id !== null
                && (int) $delivery->courier_id === (int) $actor->getKey();

            return $isAssigned && $delivery->status === DeliveryStatus::InTransit;
        }

        return false;
    }

    protected function failedAuthorization(): void
    {
        $delivery = $this->route('delivery');
        $actor = $this->user();

        // Distinguish state-based rejection from actor-based rejection.
        // If the delivery is terminal, use the existing status message so
        // owner/staff/courier all see the same wording (AR-38 revised).
        $message = 'You are not authorised to cancel this delivery.';
        if ($delivery instanceof Delivery
            && ! $delivery->status->canTransitionTo(DeliveryStatus::Cancelled)) {
            $message = 'This delivery cannot be cancelled from its current status.';
        } elseif ($actor instanceof User && $actor->role === UserRole::Courier) {
            $message = 'A courier may only cancel a delivery they are actively transporting.';
        }

        throw new HttpResponseException(
            redirect()
                ->route('deliveries.show', $this->route('delivery'))
                ->withErrors([
                    'status' => $message,
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
