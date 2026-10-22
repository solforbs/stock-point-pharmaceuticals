<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The transport (delivery) cost of a purchase order. It is shared across the
 * lines by value, so each product's landed price — what it really costs on
 * the shelf — is known on the order and carried into the goods receipt.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->decimal('transport_cost', 18, 4)->default(0)->after('expected_date');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn('transport_cost');
        });
    }
};
