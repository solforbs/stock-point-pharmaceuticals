<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The transport an invoice carries comes from its purchase order (the
 * order's transport, shared over the invoiced quantities), never typed at
 * the match, so the payable is exactly the order's value including it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->decimal('transport_total', 18, 4)->default(0)->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->dropColumn('transport_total');
        });
    }
};
