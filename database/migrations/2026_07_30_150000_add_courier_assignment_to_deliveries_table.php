<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Packet 09: add courier assignment and dispatch/delivered timestamps to the
 * deliveries table.
 *
 *   - `courier_id`      : nullable FK to users.id (restrict on update/delete).
 *                         Nullable while the delivery is `draft`; required at
 *                         `scheduled` per AR-37 (enforced by the scheduler,
 *                         not the database).
 *   - `dispatched_at`   : nullable UTC timestamp set when the assigned courier
 *                         transitions the delivery to `in_transit` (AR-41).
 *   - `delivered_at`    : nullable UTC timestamp set when the assigned courier
 *                         transitions the delivery to `delivered` (AR-35).
 *
 * Existing rows (draft, scheduled, cancelled) from Packets 07 and 08 will
 * receive NULL for all three columns. Legacy `scheduled` deliveries created
 * before this packet therefore have no assigned courier and cannot advance to
 * `in_transit`; the intended operational recovery is cancel + create-new per
 * AR-36. This is acceptable prototype behaviour.
 *
 * Compatible with both MySQL 8.0 and SQLite. Reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('deliveries', function (Blueprint $table): void {
            $table
                ->foreignId('courier_id')
                ->nullable()
                ->after('created_by_user_id')
                ->constrained('users')
                ->restrictOnUpdate()
                ->restrictOnDelete();

            $table
                ->timestamp('dispatched_at')
                ->nullable()
                ->after('courier_id');

            $table
                ->timestamp('delivered_at')
                ->nullable()
                ->after('dispatched_at');

            // Supports courier dashboard queries that filter by dispatched_at.
            $table->index('dispatched_at', 'deliveries_dispatched_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('deliveries', function (Blueprint $table): void {
            $table->dropIndex('deliveries_dispatched_at_index');
            $table->dropColumn(['delivered_at', 'dispatched_at']);
            $table->dropForeign(['courier_id']);
            $table->dropColumn('courier_id');
        });
    }
};
