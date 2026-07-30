<?php

declare(strict_types=1);

namespace App\Domain\Telemetry;

use App\Models\Delivery;
use App\Models\TelemetryRecord;

/**
 * Read-only projector that resolves "the latest position for a
 * delivery" and shapes it for the two live-map polling endpoints
 * (AR-57).
 *
 * The service is deliberately transport-agnostic: it returns plain
 * arrays that the two controllers wrap in a JSON response. The staff
 * variant carries the full telemetry surface (`speed_kmh`,
 * `heading_degrees`, `gps_timestamp`); the customer variant carries
 * only what the public tracking page needs (`latitude`, `longitude`,
 * `received_at`).
 *
 * A single query is issued per delivery per call. There is no
 * caching; the throttle on each endpoint (`throttle:60,1`) caps load.
 */
final class LatestTelemetryProvider
{
    /**
     * Staff / owner / courier surface.
     *
     * Always returns an array with the delivery id, current status
     * string, and either the latest telemetry row (as an array) or
     * `null` when no rows exist. Unlike the customer surface, the
     * staff surface does not gate on delivery status: an owner or
     * dispatcher may inspect the last known position of a delivered
     * or cancelled delivery for review purposes.
     *
     * @return array{
     *     delivery_id: int,
     *     status: string,
     *     latest: array{
     *         latitude: float,
     *         longitude: float,
     *         speed_kmh: float|null,
     *         heading_degrees: float|null,
     *         gps_timestamp: string,
     *         received_at: string,
     *     }|null,
     * }
     */
    public function forStaff(Delivery $delivery): array
    {
        $record = $this->latestRecord($delivery);

        return [
            'delivery_id' => (int) $delivery->getKey(),
            'status' => $delivery->status->value,
            'latest' => $record === null ? null : [
                'latitude' => (float) $record->latitude,
                'longitude' => (float) $record->longitude,
                'speed_kmh' => $record->speed_kmh === null
                    ? null
                    : (float) $record->speed_kmh,
                'heading_degrees' => $record->heading_degrees === null
                    ? null
                    : (float) $record->heading_degrees,
                'gps_timestamp' => $this->formatTimestamp(
                    $record->getRawOriginal('gps_timestamp'),
                ),
                'received_at' => $this->formatTimestamp(
                    $record->getRawOriginal('received_at'),
                ),
            ],
        ];
    }

    /**
     * Customer (public tracking) surface.
     *
     * Returns `latest: null` for any delivery not in `in_transit`
     * status: customers see a courier's live position only while the
     * delivery is on the road (AR-57). The response never includes
     * `speed_kmh` or `heading_degrees` — customer-facing UI does not
     * surface those fields.
     *
     * @return array{
     *     delivery_id: int,
     *     status: string,
     *     latest: array{
     *         latitude: float,
     *         longitude: float,
     *         received_at: string,
     *     }|null,
     * }
     */
    public function forCustomer(Delivery $delivery): array
    {
        $isInTransit = $delivery->status->value === 'in_transit';

        $record = $isInTransit ? $this->latestRecord($delivery) : null;

        return [
            'delivery_id' => (int) $delivery->getKey(),
            'status' => $delivery->status->value,
            'latest' => $record === null ? null : [
                'latitude' => (float) $record->latitude,
                'longitude' => (float) $record->longitude,
                'received_at' => $this->formatTimestamp(
                    $record->getRawOriginal('received_at'),
                ),
            ],
        ];
    }

    /**
     * Fetch the newest `telemetry_records` row for a delivery, or null.
     *
     * Ordering is by `received_at` descending, matching the composite
     * index `telemetry_records_delivery_received_index` established in
     * the Packet 11 migration.
     */
    private function latestRecord(Delivery $delivery): ?TelemetryRecord
    {
        return TelemetryRecord::query()
            ->where('delivery_id', $delivery->getKey())
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Render a raw datetime column as an ISO-8601 UTC string.
     *
     * The `telemetry_records.gps_timestamp` and `received_at` columns
     * are persisted in UTC. The Eloquent `datetime` cast re-hydrates
     * them into the app timezone (Asia/Jakarta), which is fine for
     * views but not what the JSON contract promises to clients.
     * Consumers should always receive a canonical UTC string ending
     * in `Z`, so this helper reads the raw value straight from the
     * database and formats it via `Y-m-d\TH:i:s\Z`.
     */
    private function formatTimestamp(mixed $raw): string
    {
        if ($raw === null) {
            return '';
        }

        // Raw column values are strings like "2026-07-30 12:34:56" for
        // both MySQL and SQLite. Interpret them as UTC and emit as
        // ISO-8601 with a trailing `Z` marker.
        $ts = new \DateTimeImmutable((string) $raw, new \DateTimeZone('UTC'));

        return $ts->format('Y-m-d\TH:i:s\Z');
    }
}
