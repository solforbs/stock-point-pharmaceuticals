<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Returns name the exact line and why it came back: a reason code and
 * free-text remarks per returned line, customer and supplier alike. A
 * product description carries the key features of non-pharma items
 * (devices, cosmetics, sundries) that have no strength to identify them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('description', 500)->nullable()->after('strength');
        });

        Schema::table('customer_return_lines', function (Blueprint $table) {
            $table->string('return_reason', 30)->nullable()->after('disposition');
            $table->string('remarks', 500)->nullable()->after('return_reason');
        });

        Schema::table('supplier_return_lines', function (Blueprint $table) {
            $table->string('return_reason', 30)->nullable()->after('unit_cost');
            $table->string('remarks', 500)->nullable()->after('return_reason');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_return_lines', fn (Blueprint $table) => $table->dropColumn(['return_reason', 'remarks']));
        Schema::table('customer_return_lines', fn (Blueprint $table) => $table->dropColumn(['return_reason', 'remarks']));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('description'));
    }
};
