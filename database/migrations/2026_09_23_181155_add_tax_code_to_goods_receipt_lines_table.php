<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 13 — the VAT treatment is read off the supplier's invoice as the
 * goods are received, so the receipt records which treatment was captured
 * and the product's own tax code follows from it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->foreignUuid('tax_code_id')->nullable()->after('discount_pct')->constrained('tax_codes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tax_code_id');
        });
    }
};
