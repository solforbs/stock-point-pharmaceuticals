<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sale_id')->constrained('sales')->cascadeOnDelete();
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
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignUuid('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
            $table->decimal('tax_rate', 9, 4)->default(0);
            $table->decimal('tax_amount', 18, 4)->default(0);
            $table->decimal('line_total', 18, 4);

            $table->decimal('unit_cost', 18, 4);
            $table->decimal('line_cost', 18, 4);

            $table->boolean('is_bonus')->default(false);
            $table->boolean('fefo_overridden')->default(false);
            $table->string('override_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_lines');
    }
};
