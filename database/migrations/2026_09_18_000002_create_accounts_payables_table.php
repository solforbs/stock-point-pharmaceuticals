<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Part 9.4 — "Only a matched invoice creates an accounts_payable entry."
     * Mirrors accounts_receivables: a signed running sub-ledger per supplier.
     */
    public function up(): void
    {
        Schema::create('accounts_payables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->enum('txn_type', ['INVOICE', 'PAYMENT', 'ADJUSTMENT', 'DEBIT_NOTE'])->default('INVOICE');
            $table->foreignUuid('supplier_invoice_id')->nullable()->constrained('supplier_invoices')->nullOnDelete();
            $table->uuid('supplier_payment_id')->nullable();
            $table->decimal('amount', 18, 4); // signed: +invoice, -payment/debit note
            $table->decimal('balance_after', 18, 4);
            $table->date('due_date')->nullable();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['supplier_id', 'created_at']);
        });

        Schema::create('supplier_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->enum('method', ['CASH', 'MPESA', 'BANK', 'CHEQUE'])->default('BANK');
            $table->string('reference')->nullable();
            $table->decimal('amount', 18, 4);
            $table->foreignId('paid_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('paid_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('accounts_payables');
    }
};
