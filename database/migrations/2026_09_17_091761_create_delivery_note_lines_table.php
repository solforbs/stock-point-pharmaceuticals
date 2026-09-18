<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per sales_order_line dispatched (batch splits live in
        // delivery_note_line_batch_allocations, mirroring sale_line_batch_allocations),
        // so discount/tax totals never need proration across batches.
        Schema::create('delivery_note_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('delivery_note_id')->constrained('delivery_notes')->cascadeOnDelete();
            $table->foreignUuid('sales_order_line_id')->constrained('sales_order_lines')->restrictOnDelete();
            $table->unsignedInteger('line_number');
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignUuid('uom_id')->constrained('units_of_measure')->restrictOnDelete();
            $table->decimal('qty', 18, 4);
            $table->decimal('qty_base', 18, 4);

            $table->decimal('list_price', 18, 4);
            $table->decimal('unit_price', 18, 4);
            $table->decimal('discount_amount', 18, 4)->default(0);
            $table->decimal('discount_pct', 9, 4)->default(0);
            $table->string('discount_source')->nullable();

            $table->foreignUuid('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
            $table->decimal('tax_rate', 9, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('line_total', 18, 4);

            $table->decimal('unit_cost', 18, 4);
            $table->decimal('line_cost', 18, 4);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_note_lines');
    }
};
