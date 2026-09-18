<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Immutable, append-only — the single source of truth (Part 7.1).
        // A mistake is corrected by posting a compensating row that
        // references the erroneous one (reverses_ledger_id), never by
        // UPDATE or DELETE.
        Schema::create('stock_ledgers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->enum('txn_type', [
                'GRN_RECEIPT', 'PURCHASE_RETURN', 'SALE', 'SALE_BONUS', 'SALE_VOID', 'DISPENSING',
                'CUSTOMER_RETURN', 'TRANSFER_OUT', 'TRANSFER_IN', 'ADJUSTMENT_UP',
                'ADJUSTMENT_DOWN', 'COUNT_VARIANCE', 'QUARANTINE_IN', 'QUARANTINE_OUT',
                'EXPIRY_WRITE_OFF', 'DAMAGE_WRITE_OFF', 'RECALL_BLOCK',
                'REPACK_OUT', 'REPACK_IN', 'OPENING_BALANCE',
            ]);
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('batch_id')->constrained('product_batches')->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUuid('location_id')->nullable()->constrained('locations')->nullOnDelete();

            $table->decimal('qty_base', 18, 4); // signed
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('total_cost', 18, 4)->default(0);

            $table->string('source_doc_type');
            $table->uuid('source_doc_id');
            $table->uuid('source_doc_line_id')->nullable();
            $table->foreignUuid('reverses_ledger_id')->nullable()->constrained('stock_ledgers')->nullOnDelete();

            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->dateTime('txn_datetime');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'batch_id', 'store_id', 'txn_datetime'], 'stock_ledgers_pbst_idx');
            $table->index(['source_doc_type', 'source_doc_id']);
            $table->index('txn_datetime');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_ledgers');
    }
};
