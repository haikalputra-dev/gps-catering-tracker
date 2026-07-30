<?php

declare(strict_types=1);

namespace App\Http\Requests\Delivery;

use App\Domain\Delivery\DeliveryStatus;
use App\Models\Delivery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Guards the POST /deliveries/{delivery}/schedule endpoint.
 *
 * No form body is required; the service layer captures snapshots and
 * generates the receipt from the delivery's existing fields.
 */
class ScheduleDeliveryRequest extends FormRequest
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
                    'status' => 'Only draft deliveries can be scheduled.',
                ]),
        );
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [];
    }
}
