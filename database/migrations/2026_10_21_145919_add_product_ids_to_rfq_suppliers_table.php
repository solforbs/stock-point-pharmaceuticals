<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which of the request's products each supplier is asked to quote. NULL is
 * the whole list; otherwise the supplier's PDF and quote cover only these.
 * Kept by product rather than line id, so it survives the lines being
 * replaced while the request is a draft.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfq_suppliers', function (Blueprint $table) {
            $table->json('product_ids')->nullable()->after('supplier_id');
        });
    }

    public function down(): void
    {
        Schema::table('rfq_suppliers', function (Blueprint $table) {
            $table->dropColumn('product_ids');
        });
    }
};
