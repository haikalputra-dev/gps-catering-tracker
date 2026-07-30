<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TelemetryRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single accepted GPS ping tied to an active delivery.
 *
 * Rows are only created when the ingesting device is bound to a
 * courier and that courier has an active delivery (AR-51). Idle
 * submissions are accepted with 204 and no row is inserted.
 *
 * The table has no `created_at`/`updated_at` columns; `received_at`
 * is the server-side authoritative timestamp. The static
 * `$timestamps = false` opt-out below matches the schema.
 */
class TelemetryRecord extends Model
{
    /** @use HasFactory<TelemetryRecordFactory> */
    use HasFactory;

    /**
     * The `telemetry_records` table is a write-heavy log-shape table;
     * standard Eloquent timestamps are intentionally disabled.
     */
    public $timestamps = false;

    protected $fillable = [
        'device_id',
        'delivery_id',
        'latitude',
        'longitude',
        'speed_kmh',
        'heading_degrees',
        'gps_timestamp',
        'received_at',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'speed_kmh' => 'decimal:2',
            'heading_degrees' => 'decimal:2',
            'gps_timestamp' => 'datetime',
            'received_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    protected static function newFactory(): TelemetryRecordFactory
    {
        return TelemetryRecordFactory::new();
    }
}
