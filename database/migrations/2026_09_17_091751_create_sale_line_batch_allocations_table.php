<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_line_batch_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_line_id')->constrained('sale_lines')->cascadeOnDelete();
            $table->foreignUuid('batch_id')->constrained('product_batches')->restrictOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->restrictOnDelete();
            $table->decimal('qty_base', 18, 4);
            $table->decimal('unit_cost', 18, 4);
            $table->boolean('is_bonus')->default(false);
            $table->boolean('fefo_overridden')->default(false);
            $table->string('override_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_line_batch_allocations');
    }
};
