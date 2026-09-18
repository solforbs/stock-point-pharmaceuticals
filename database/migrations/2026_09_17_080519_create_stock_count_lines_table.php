<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_count_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('stock_count_id')->constrained('stock_counts')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('batch_id')->constrained('product_batches')->cascadeOnDelete();
            $table->decimal('system_qty', 18, 4);
            $table->decimal('counted_qty', 18, 4)->nullable();
            $table->decimal('variance_qty', 18, 4)->nullable();
            $table->decimal('variance_value', 18, 4)->nullable();
            $table->string('reason_code')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_count_lines');
    }
};
