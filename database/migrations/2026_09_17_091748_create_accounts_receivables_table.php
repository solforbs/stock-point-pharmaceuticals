<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Transaction-level AR ledger (not schema'd explicitly in V5/V6's
        // Part 20, which only names the table) — an append-only trail behind
        // customer_credits.current_balance, the same ledger-first pattern as
        // stock_ledger behind stock_balance.
        Schema::create('accounts_receivables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->enum('txn_type', ['INVOICE', 'PAYMENT', 'ADJUSTMENT', 'CREDIT_NOTE'])->default('INVOICE');
            $table->uuid('sale_id')->nullable();
            $table->uuid('payment_id')->nullable();
            $table->decimal('amount', 18, 4); // signed: +invoice/adjustment up, -payment/credit note
            $table->decimal('balance_after', 18, 4);
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts_receivables');
    }
};
