<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Packet 11: create the `device_assignments` table.
 *
 * 1:1 exclusive binding between a device and a courier with full
 * reassignment history (AR-50). Each row represents one continuous
 * period during which a device is bound to a courier. A row is "open"
 * (currently active) when `unassigned_at` is NULL and "closed" when
 * `unassigned_at` is populated. The assignment service enforces at
 * most one open row per device and at most one open row per courier;
 * this is a domain invariant not a database constraint, so future
 * changes can lift or refine the rule without a schema migration.
 *
 *   - `device_id`      : FK to devices.id, cascade-restricted.
 *   - `courier_id`     : FK to users.id, restrict-on-delete. The
 *                        assignment service verifies the user's role
 *                        is Courier and their is_active flag is true
 *                        before opening an assignment.
 *   - `assigned_at`    : UTC timestamp of binding start.
 *   - `unassigned_at`  : nullable UTC timestamp of binding end.
 *   - `assigned_by_user_id`   : owner who opened the assignment.
 *   - `unassigned_by_user_id` : owner who closed the assignment.
 *   - `notes`          : optional operator memo captured at
 *                        unassignment (e.g. "device returned for
 *                        battery replacement").
 *
 * Compatible with both MySQL 8.0 and SQLite. Reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_assignments', function (Blueprint $table): void {
            $table->id();
            $table
                ->foreignId('device_id')
                ->constrained('devices')
                ->restrictOnUpdate()
                ->restrictOnDelete();
            $table
                ->foreignId('courier_id')
                ->constrained('users')
                ->restrictOnUpdate()
                ->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('unassigned_at')->nullable();
            $table
                ->foreignId('assigned_by_user_id')
                ->constrained('users')
                ->restrictOnUpdate()
                ->restrictOnDelete();
            $table
                ->foreignId('unassigned_by_user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnUpdate()
                ->restrictOnDelete();
            $table->string('notes', 1000)->nullable();
            $table->timestamps();

            // Common queries: "the current assignment for a device" and
            // "the current assignment for a courier". A composite index
            // on (device_id, unassigned_at) and (courier_id,
            // unassigned_at) both make the "open row" lookup a single
            // seek. NULL sorts to the "open" side in both MySQL and
            // SQLite by default.
            $table->index(['device_id', 'unassigned_at'], 'device_assignments_device_open_index');
            $table->index(['courier_id', 'unassigned_at'], 'device_assignments_courier_open_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_assignments');
    }
};
