<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Part 12.5 — a receipt is reconciled against the bank / M-PESA
     * statement line it appeared on: who matched it, the statement
     * reference and date, and the amount the statement showed.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('reconciled_by')->nullable()->after('reconciled_at')->constrained('users')->nullOnDelete();
            $table->string('reconciliation_ref')->nullable()->after('reconciled_by');
            $table->date('statement_date')->nullable()->after('reconciliation_ref');
            $table->decimal('statement_amount', 18, 4)->nullable()->after('statement_date');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reconciled_by');
            $table->dropColumn(['reconciliation_ref', 'statement_date', 'statement_amount']);
        });
    }
};
