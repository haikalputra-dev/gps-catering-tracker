<?php

declare(strict_types=1);

namespace App\Domain\Device;

use DateTimeInterface;

/**
 * Immutable value object carrying a single, already-validated telemetry
 * submission from HTTP into {@see TelemetryIngester}.
 *
 * The form request is responsible for range and type validation;
 * this DTO simply carries the values across the layer boundary so the
 * ingester does not depend on the HTTP request object.
 */
final class TelemetryPayload
{
    public function __construct(
        public readonly float $latitude,
        public readonly float $longitude,
        public readonly DateTimeInterface $gpsTimestamp,
        public readonly ?float $speedKmh = null,
        public readonly ?float $headingDegrees = null,
    ) {
    }

    /**
     * Construct from a validated array (typically the output of the
     * telemetry form request's `validated()`).
     *
     * @param array{
     *     latitude: float|string,
     *     longitude: float|string,
     *     gps_timestamp: DateTimeInterface|string,
     *     speed_kmh?: float|string|null,
     *     heading_degrees?: float|string|null,
     * } $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            latitude: (float) $data['latitude'],
            longitude: (float) $data['longitude'],
            gpsTimestamp: $data['gps_timestamp'] instanceof DateTimeInterface
                ? $data['gps_timestamp']
                : new \DateTimeImmutable((string) $data['gps_timestamp']),
            speedKmh: isset($data['speed_kmh']) ? (float) $data['speed_kmh'] : null,
            headingDegrees: isset($data['heading_degrees']) ? (float) $data['heading_degrees'] : null,
        );
    }
}
