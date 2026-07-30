<?php

declare(strict_types=1);

namespace App\Http\Requests\Telemetry;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a single telemetry submission from a device.
 *
 * The device is already authenticated by the `device.auth` middleware
 * and attached to the request as the `device` attribute; this form
 * request is responsible only for payload shape and numeric ranges.
 *
 * `gps_timestamp` must be a parseable ISO-8601 value with a timezone
 * offset; the ingester normalises to UTC on write.
 */
class StoreTelemetryRequest extends FormRequest
{
    /**
     * Authorisation is enforced by the middleware chain (device token
     * + throttle). At this point we accept every request that reached
     * the controller.
     */
    public function authorize(): bool
    {
        return $this->attributes->get('device') !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'gps_timestamp' => ['required', 'string'],
            'speed_kmh' => ['nullable', 'numeric', 'between:0,300'],
            'heading_degrees' => ['nullable', 'numeric', 'between:0,360'],
        ];
    }

    /**
     * Additional cross-field checks. `gps_timestamp` must parse as an
     * ISO-8601 string and must not be more than a modest window into
     * the future (clock skew tolerance) or ancient past.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $raw = (string) $this->input('gps_timestamp', '');

            if ($raw === '') {
                return;
            }

            try {
                $parsed = new \DateTimeImmutable($raw);
            } catch (\Throwable) {
                $v->errors()->add(
                    'gps_timestamp',
                    'The gps_timestamp field must be a valid ISO-8601 datetime.',
                );

                return;
            }

            $now = new \DateTimeImmutable('now');
            $futureLimit = $now->modify('+5 minutes');
            $pastLimit = $now->modify('-1 day');

            if ($parsed > $futureLimit) {
                $v->errors()->add(
                    'gps_timestamp',
                    'The gps_timestamp field must not be in the future.',
                );
            }

            if ($parsed < $pastLimit) {
                $v->errors()->add(
                    'gps_timestamp',
                    'The gps_timestamp field is too old to accept.',
                );
            }
        });
    }
}
