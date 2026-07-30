<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Packet 11: create the `devices` table.
 *
 * Physical GPS trackers registered by the owner. Each row is a hardware
 * identity, not a courier assignment; assignment history lives in a
 * separate `device_assignments` table so a device can be re-bound to a
 * new courier while keeping the audit trail. Devices are never hard
 * deleted; lifecycle is managed via `is_active`.
 *
 *   - `identifier`      : short human-readable label (e.g. serial).
 *                         Unique per active record; stored trimmed.
 *   - `model`           : optional hardware model string (e.g. ESP32-A).
 *   - `hardware_version`: optional revision string.
 *   - `api_token`       : 40-char random Bearer token (AR-47 revised).
 *                         Unique + indexed. Plaintext prototype storage.
 *   - `is_active`       : deactivate to revoke ingest and admin access.
 *   - `last_seen_at`    : nullable UTC timestamp updated by the
 *                         ingester on every accepted request (whether
 *                         persisted or discarded per AR-51).
 *   - `notes`           : optional operator memo.
 *
 * Compatible with both MySQL 8.0 and SQLite. Reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table): void {
            $table->id();
            $table->string('identifier', 64);
            $table->string('model', 64)->nullable();
            $table->string('hardware_version', 32)->nullable();
            $table->string('api_token', 64);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->timestamps();

            $table->unique('identifier', 'devices_identifier_unique');
            $table->unique('api_token', 'devices_api_token_unique');
            $table->index('is_active', 'devices_is_active_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
