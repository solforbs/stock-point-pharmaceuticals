<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Part 10.6 — warehouse locations: a readable name, what kind of slot it
     * is (a cold shelf matters for put-away), and deactivation instead of
     * deletion so historical ledger rows keep their location.
     */
    public function up(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->string('name')->nullable()->after('code');
            $table->enum('location_type', ['BIN', 'SHELF', 'PALLET', 'COLD_SHELF'])->default('BIN')->after('name');
            $table->boolean('is_active')->default(true)->after('capacity');
        });
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn(['name', 'location_type', 'is_active']);
        });
    }
};
