<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Requisition → quotation → purchase order: an RFQ raised from a
 * requisition remembers it, so the purchase order the award raises can
 * point back to what was asked for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->foreignUuid('requisition_id')->nullable()->after('branch_id')->constrained('requisitions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rfqs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requisition_id');
        });
    }
};
