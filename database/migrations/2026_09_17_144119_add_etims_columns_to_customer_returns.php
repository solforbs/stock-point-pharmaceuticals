<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 13.6 — credit notes transmit to eTIMS referencing the original
 * control code, so they carry the same status columns as sales.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_returns', function (Blueprint $table) {
            $table->string('etims_status')->nullable()->index()->after('cost_total');
            $table->string('etims_control_code')->nullable()->after('etims_status');
            $table->string('etims_invoice_number')->nullable()->after('etims_control_code');
            $table->timestamp('etims_submitted_at')->nullable()->after('etims_invoice_number');
            $table->string('etims_error')->nullable()->after('etims_submitted_at');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->index('etims_status');
        });
    }

    public function down(): void
    {
        Schema::table('sales', fn (Blueprint $table) => $table->dropIndex(['etims_status']));
        Schema::table('customer_returns', fn (Blueprint $table) => $table->dropColumn(['etims_status', 'etims_control_code', 'etims_invoice_number', 'etims_submitted_at', 'etims_error']));
    }
};
