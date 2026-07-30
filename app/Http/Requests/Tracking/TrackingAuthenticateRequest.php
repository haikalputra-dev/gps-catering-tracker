<?php

declare(strict_types=1);

namespace App\Http\Requests\Tracking;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validates the customer tracking authentication payload (Packet 10).
 *
 * Behavior:
 *  - Public endpoint: `authorize()` always returns `true`.
 *  - Two fields: `receipt_number`, `phone_last_four`.
 *  - Errors are collapsed to a single generic form-level message so
 *    the response cannot leak which field was wrong (AR-42 revised).
 *
 * The receipt is trimmed and upper-cased in `prepareForValidation`,
 * but the deep normalization (hyphens, stripping of separators) is the
 * responsibility of `TrackingAuthenticator`. This request only guards
 * against grossly malformed input.
 */
class TrackingAuthenticateRequest extends FormRequest
{
    /**
     * Text of the single generic error used for every validation
     * failure on this endpoint. Kept as a class constant so tests and
     * the controller can reference the same string.
     */
    public const GENERIC_ERROR = 'Invalid receipt or phone digits. Please check and try again.';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'receipt_number' => ['required', 'string', 'max:30'],
            'phone_last_four' => ['required', 'string', 'size:4', 'regex:/^\d{4}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'receipt_number' => is_string($this->input('receipt_number'))
                ? strtoupper(trim((string) $this->input('receipt_number')))
                : $this->input('receipt_number'),
            'phone_last_four' => is_string($this->input('phone_last_four'))
                ? trim((string) $this->input('phone_last_four'))
                : $this->input('phone_last_four'),
        ]);
    }

    /**
     * Collapse every rule failure - across both fields - into a single
     * form-level error keyed as `form`. This prevents an attacker from
     * learning whether the receipt vs. the phone was malformed.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            redirect()
                ->route('tracking.form')
                ->withInput($this->only(['receipt_number']))
                ->withErrors(['form' => self::GENERIC_ERROR]),
        );
    }
}
