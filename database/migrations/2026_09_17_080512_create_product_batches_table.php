<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('batch_number');
            $table->string('manufacturer_batch_ref')->nullable();
            $table->date('expiry_date');
            $table->date('manufacture_date')->nullable();
            $table->foreignUuid('manufacturer_id')->nullable()->constrained('manufacturers')->nullOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            // grn_line_id -> goods_receipt_lines, added once Procurement exists.
            $table->uuid('grn_line_id')->nullable();
            $table->decimal('unit_cost', 18, 4)->default(0);
            $table->decimal('landed_unit_cost', 18, 4)->default(0);
            // Part 8.2 state machine.
            $table->enum('status', [
                'PENDING_QC', 'RELEASED', 'REJECTED', 'QUARANTINED',
                'EXPIRED', 'RECALLED', 'RETURNED_TO_SUPPLIER', 'DISPOSED',
            ])->default('PENDING_QC');
            $table->foreignId('qc_released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('qc_released_at')->nullable();
            // coa_document_id -> documents, added once the Documents module exists.
            $table->uuid('coa_document_id')->nullable();
            $table->foreignUuid('storage_condition_id')->nullable()->constrained('storage_conditions')->nullOnDelete();
            $table->timestamps();

            $table->unique(['organisation_id', 'product_id', 'batch_number', 'supplier_id'], 'product_batches_unique_row');
            $table->index(['product_id', 'status', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_batches');
    }
};
