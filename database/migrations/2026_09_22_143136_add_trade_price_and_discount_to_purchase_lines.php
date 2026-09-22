<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distributors quote a gross trade price less a purchase discount (trade
 * 450, less 7%, buys at about 418.50). Both are kept beside the net cost on
 * order and receipt lines so the terms the supplier gave stay on record;
 * the net unit cost remains the figure that posts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_lines', function (Blueprint $table) {
            $table->decimal('trade_price', 18, 4)->nullable()->after('unit_price');
            $table->decimal('discount_pct', 7, 3)->nullable()->after('trade_price');
        });

        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->decimal('trade_price', 18, 4)->nullable()->after('unit_cost');
            $table->decimal('discount_pct', 7, 3)->nullable()->after('trade_price');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_lines', fn (Blueprint $table) => $table->dropColumn(['trade_price', 'discount_pct']));
        Schema::table('purchase_order_lines', fn (Blueprint $table) => $table->dropColumn(['trade_price', 'discount_pct']));
    }
};
