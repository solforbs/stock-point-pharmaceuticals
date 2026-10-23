<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How the supplier on a purchase order was chosen: by competitive bid
 * analysis, or single sourced (no bids) for a recorded reason such as the
 * supplier delivering direct or being the only one holding the item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('sourcing_method', 20)->nullable()->after('rfq_id');
            $table->string('single_source_reason', 30)->nullable()->after('sourcing_method');
            $table->text('single_source_justification')->nullable()->after('single_source_reason');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['sourcing_method', 'single_source_reason', 'single_source_justification']);
        });
    }
};
