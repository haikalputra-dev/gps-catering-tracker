<?php

declare(strict_types=1);

namespace App\Domain\Device;

use App\Models\Delivery;
use App\Models\Device;
use App\Models\TelemetryRecord;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Accepts an authenticated telemetry submission and either persists a
 * {@see TelemetryRecord} row or drops it (accept-and-discard).
 *
 * Behaviour matrix (AR-51):
 *
 *   Device has no open assignment            → discard, return null
 *   Assigned courier has no active delivery  → discard, return null
 *   Assigned courier has an active delivery  → insert a row, return it
 *
 * The ingester also updates `devices.last_seen_at` on every accepted
 * submission so the admin index can render freshness even when the
 * courier is idle. AR-49 rate limiting is handled by named limiter in
 * `bootstrap/app.php`; the ingester itself is oblivious to throttling.
 *
 * "Active delivery" is defined by {@see Delivery::activeForCourier()}:
 * status is `scheduled` or `in_transit`. If a courier happens to have
 * more than one active delivery, the most-recently dispatched row wins
 * — this is defensive; production uses a concurrency cap of one, but
 * tests can construct edge cases.
 */
class TelemetryIngester
{
    /**
     * Ingest a single payload for the given authenticated $device.
     *
     * Returns the persisted record when a delivery is found, or `null`
     * when the submission was accepted but discarded. Callers should
     * translate both outcomes to the same HTTP status (204).
     */
    public function ingest(Device $device, TelemetryPayload $payload): ?TelemetryRecord
    {
        $device->forceFill(['last_seen_at' => now()])->save();

        $currentCourierId = $this->currentCourierId($device);

        if ($currentCourierId === null) {
            return null;
        }

        $activeDelivery = $this->activeDeliveryFor($currentCourierId);

        if ($activeDelivery === null) {
            return null;
        }

        return TelemetryRecord::query()->create([
            'device_id' => $device->id,
            'delivery_id' => $activeDelivery->id,
            'latitude' => $payload->latitude,
            'longitude' => $payload->longitude,
            'speed_kmh' => $payload->speedKmh,
            'heading_degrees' => $payload->headingDegrees,
            'gps_timestamp' => $this->utc($payload->gpsTimestamp),
            'received_at' => now(),
        ]);
    }

    /**
     * The courier currently bound to $device, or null if unbound.
     */
    private function currentCourierId(Device $device): ?int
    {
        $assignment = $device->assignments()
            ->whereNull('unassigned_at')
            ->first();

        return $assignment?->courier_id !== null
            ? (int) $assignment->courier_id
            : null;
    }

    /**
     * The most-recently dispatched active delivery for the courier,
     * or null when no active delivery exists.
     */
    private function activeDeliveryFor(int $courierId): ?Delivery
    {
        /** @var Delivery|null $delivery */
        $delivery = Delivery::query()
            ->activeForCourier($courierId)
            ->orderByDesc('dispatched_at')
            ->orderByDesc('id')
            ->first();

        return $delivery;
    }

    /**
     * Normalise the client-supplied GPS timestamp to UTC before
     * persisting. The client is required to send an ISO-8601 value
     * with a timezone offset; the form request already validates that.
     */
    private function utc(DateTimeInterface $timestamp): DateTimeImmutable
    {
        return DateTimeImmutable::createFromInterface($timestamp)
            ->setTimezone(new \DateTimeZone('UTC'));
    }
}
