<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('price_list_id')->constrained('price_lists')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('uom_id')->constrained('units_of_measure')->cascadeOnDelete();

            // Part 4.3 — four factor types. unit_price is the resolved price
            // when factor_type is FIXED; for the other three it is
            // left null and PricingEngine computes it from cost/list price
            // at quote time (cost basis is always landed/WAC, Part 21.9/4.3).
            $table->enum('factor_type', ['FIXED', 'COST_PLUS_MARKUP', 'TARGET_MARGIN', 'LIST_RELATIVE'])
                ->default('FIXED');
            $table->decimal('unit_price', 18, 4)->nullable();
            $table->decimal('factor_value', 9, 4)->nullable(); // markup/margin fraction, or list-relative multiplier

            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->timestamps();

            $table->unique(['price_list_id', 'product_id', 'uom_id', 'effective_from'], 'product_prices_unique_row');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
