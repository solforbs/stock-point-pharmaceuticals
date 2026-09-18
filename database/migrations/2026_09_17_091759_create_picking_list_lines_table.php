<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('picking_list_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('picking_list_id')->constrained('picking_lists')->cascadeOnDelete();
            $table->foreignUuid('sales_order_line_id')->constrained('sales_order_lines')->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignUuid('batch_id')->constrained('product_batches')->restrictOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->restrictOnDelete();
            $table->decimal('qty_to_pick_base', 18, 4);
            $table->decimal('qty_picked_base', 18, 4)->default(0);
            $table->decimal('unit_cost', 18, 4);
            $table->enum('status', ['PENDING', 'PICKED', 'SHORT'])->default('PENDING');
            $table->timestamp('picked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('picking_list_lines');
    }
};
