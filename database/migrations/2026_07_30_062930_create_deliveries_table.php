<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliveries', function (Blueprint $table): void {
            $table->id();

            // Receipt number is null while the delivery is a draft. It is
            // assigned atomically at `draft -> scheduled` and is immutable
            // once set (AR-24). Unique across the entire table.
            $table->string('receipt_number', 20)->nullable()->unique();

            // Delivery lifecycle status (AR-23). String column so migrations
            // remain portable between MySQL 8 and SQLite (:memory:) test runs.
            $table->string('status', 20)->default('draft')->index();

            // Live foreign keys are preserved even after snapshots are taken;
            // restrict on delete keeps historical deliveries readable.
            $table->foreignId('kitchen_id')
                ->constrained('kitchens')
                ->restrictOnDelete();
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();

            // Scheduled dispatch time stored in UTC (AR-28). Nullable while
            // the delivery is a draft. Indexed to support the listing order
            // "non-terminal first, then scheduled_at asc".
            $table->timestamp('scheduled_at')->nullable()->index();

            // Free-form notes captured on the draft (<= 1000 chars enforced
            // in the form request).
            $table->text('notes')->nullable();

            // Kitchen snapshot fields (AR-25). Captured atomically at the
            // draft->scheduled transition; NULL while the delivery is still
            // a draft. Column widths mirror the source kitchen columns.
            $table->string('kitchen_code', 30)->nullable();
            $table->string('kitchen_name', 150)->nullable();
            $table->text('kitchen_address')->nullable();
            $table->decimal('kitchen_latitude', 10, 7)->nullable();
            $table->decimal('kitchen_longitude', 10, 7)->nullable();

            // Customer snapshot fields (AR-25). Column widths mirror the
            // source customer columns.
            $table->string('customer_name', 150)->nullable();
            $table->string('customer_phone', 25)->nullable();
            $table->text('customer_address')->nullable();
            $table->decimal('customer_latitude', 10, 7)->nullable();
            $table->decimal('customer_longitude', 10, 7)->nullable();

            // Scheduling audit trail. Populated at the draft->scheduled
            // transition alongside the snapshot capture.
            $table->foreignId('scheduled_by_user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('scheduled_at_recorded')->nullable();

            // Cancellation audit trail. Populated at the *->cancelled
            // transition. Reason length is 3..255 enforced in FormRequest.
            $table->string('cancellation_reason', 255)->nullable();
            $table->foreignId('cancelled_by_user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();

            // Creator audit. Draft creation MUST always record the actor.
            $table->foreignId('created_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
