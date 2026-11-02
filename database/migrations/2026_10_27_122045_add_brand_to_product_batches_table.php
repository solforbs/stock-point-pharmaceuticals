<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A generic product is received from several makers: each batch records
 * the brand it came as, while stock and price stay on the generic.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_batches', function (Blueprint $table) {
            $table->string('brand_name', 150)->nullable()->after('batch_number');
        });
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->string('brand_name', 150)->nullable()->after('batch_number');
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->dropColumn('brand_name');
        });
        Schema::table('product_batches', function (Blueprint $table) {
            $table->dropColumn('brand_name');
        });
    }
};
