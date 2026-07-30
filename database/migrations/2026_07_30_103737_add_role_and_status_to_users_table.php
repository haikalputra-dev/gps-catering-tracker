<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add role, phone and is_active columns to the users table.
 *
 * The role is stored as a plain string (not a database ENUM) so the
 * application layer owns the enum via {@see \App\Domain\Identity\UserRole}.
 * No default is set on the role column at the database level: every
 * application-level user creation must supply an explicit role.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('role', 20)->after('email');
            $table->string('phone', 25)->nullable()->after('role');
            $table->boolean('is_active')->default(true)->after('phone');

            $table->index('role');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['role']);
            $table->dropIndex(['is_active']);
            $table->dropColumn(['role', 'phone', 'is_active']);
        });
    }
};
