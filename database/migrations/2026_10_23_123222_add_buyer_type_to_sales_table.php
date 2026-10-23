<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who a sale was made to — walk-in, chemist, clinic, hospital, government or
 * NGO — so the price worked out at the till for that kind of buyer is kept
 * with the sale, and the till can show what that kind of buyer usually pays.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('buyer_type', 20)->nullable()->after('customer_id');
            $table->index(['branch_id', 'buyer_type']);
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'buyer_type']);
            $table->dropColumn('buyer_type');
        });
    }
};
