<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How a delivery left the warehouse: by vehicle, by motorbike, carried by
 * hand, or collected by the customer. Existing notes with a registration
 * number were vehicle deliveries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->string('delivery_mode', 20)->nullable()->after('status');
        });

        DB::table('delivery_notes')->whereNotNull('vehicle_reg')->update(['delivery_mode' => 'VEHICLE']);
    }

    public function down(): void
    {
        Schema::table('delivery_notes', function (Blueprint $table) {
            $table->dropColumn('delivery_mode');
        });
    }
};
