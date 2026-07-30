<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add Haversine distance and calculated fee to the deliveries table.
     *
     * Approved by AR-31: `distance_km` is `decimal(8, 3)`,
     * `fee_rupiah` is unsigned integer, both nullable while a delivery
     * is in `draft`. They are populated atomically during the
     * `draft -> scheduled` transition by App\Domain\Delivery\DeliveryScheduler.
     *
     * Note: no data backfill is performed. Deliveries that reached
     * `scheduled`, `cancelled`, `in_transit`, or `delivered` before
     * this migration remain with NULL distance and fee; those rows
     * predate Packet 08 and cannot be retroactively priced.
     */
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table): void {
            $table->decimal('distance_km', 8, 3)
                ->nullable()
                ->after('customer_longitude');

            $table->unsignedInteger('fee_rupiah')
                ->nullable()
                ->after('distance_km');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table): void {
            $table->dropColumn(['distance_km', 'fee_rupiah']);
        });
    }
};
