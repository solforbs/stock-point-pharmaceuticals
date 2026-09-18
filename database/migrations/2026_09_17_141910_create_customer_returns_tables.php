<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 11.1 — customer returns. Every returned unit references the original
 * sale line and batch so the credit note reverses at the original cost, and
 * every unit is dispositioned before it re-enters stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUuid('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->uuid('recall_id')->nullable()->index();
            $table->string('doc_number')->unique();
            $table->string('credit_note_number')->nullable()->unique();
            $table->enum('status', ['DRAFT', 'POSTED', 'REJECTED'])->default('DRAFT');
            $table->text('reason');
            $table->enum('refund_method', ['CASH', 'MPESA', 'BANK', 'CUSTOMER_ACCOUNT'])->nullable();
            $table->string('refund_reference')->nullable();
            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('tax_total', 18, 4)->default(0);
            $table->decimal('grand_total', 18, 4)->default(0);
            $table->decimal('cost_total', 18, 4)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('inspected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('customer_return_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_return_id')->constrained('customer_returns')->cascadeOnDelete();
            $table->foreignUuid('sale_line_id')->constrained('sale_lines')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('batch_id')->constrained('product_batches')->cascadeOnDelete();
            $table->decimal('qty_base', 18, 4);
            $table->enum('disposition', ['RESALEABLE', 'QUARANTINE', 'DESTROY', 'REJECT'])->default('QUARANTINE');
            $table->decimal('unit_price', 18, 4);   // net refund per base unit, from the original line
            $table->decimal('line_net', 18, 4);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('line_total', 18, 4);
            $table->decimal('unit_cost', 18, 4);    // the original allocation cost, never today's WAC
            $table->decimal('line_cost', 18, 4);
            $table->string('inspection_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_return_lines');
        Schema::dropIfExists('customer_returns');
    }
};
