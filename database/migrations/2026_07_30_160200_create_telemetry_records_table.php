<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Packet 11: create the `telemetry_records` table.
 *
 * Persisted GPS pings from a device tied to an active delivery. Rows
 * are only written when the ingesting device is currently assigned to
 * a courier AND that courier has an active (`scheduled` or
 * `in_transit`) delivery (AR-51). Idle submissions are accepted with
 * a 204 response and no row is written.
 *
 *   - `device_id`       : FK to devices.id, cascade-restricted.
 *   - `delivery_id`     : FK to deliveries.id, cascade-restricted.
 *                         Not-null: retention is per-delivery.
 *   - `latitude`        : decimal(10, 7). Range [-90, 90].
 *   - `longitude`       : decimal(10, 7). Range [-180, 180].
 *   - `speed_kmh`       : decimal(5, 2) NULL. Range [0, 300].
 *   - `heading_degrees` : decimal(5, 2) NULL. Range [0, 360).
 *   - `gps_timestamp`   : device-reported UTC timestamp (ISO-8601 on
 *                         the wire, cast to datetime here).
 *   - `received_at`     : server-side UTC timestamp of ingestion.
 *
 * The table has no `created_at/updated_at`; `received_at` is the
 * authoritative wall-clock. This is a write-heavy log-shaped table,
 * so the standard timestamps helper is intentionally skipped to keep
 * inserts minimal.
 *
 * Compatible with both MySQL 8.0 and SQLite. Reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetry_records', function (Blueprint $table): void {
            $table->id();
            $table
                ->foreignId('device_id')
                ->constrained('devices')
                ->restrictOnUpdate()
                ->restrictOnDelete();
            $table
                ->foreignId('delivery_id')
                ->constrained('deliveries')
                ->restrictOnUpdate()
                ->restrictOnDelete();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('speed_kmh', 5, 2)->nullable();
            $table->decimal('heading_degrees', 5, 2)->nullable();
            $table->timestamp('gps_timestamp');
            $table->timestamp('received_at');

            // Retention worker (deferred) will scan by received_at; the
            // customer status page and dashboards read by delivery id
            // ordered by received_at descending.
            $table->index('received_at', 'telemetry_records_received_at_index');
            $table->index(['delivery_id', 'received_at'], 'telemetry_records_delivery_received_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_records');
    }
};
