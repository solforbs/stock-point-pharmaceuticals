<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('uom_id')->constrained('units_of_measure')->cascadeOnDelete();
            $table->decimal('buy_qty', 18, 4)->nullable();
            $table->decimal('free_qty', 18, 4)->nullable();
            // Reward can be the same product (free_qty of it) or a different
            // one entirely (bonus_product_id) — e.g. buy antibiotics, get a
            // branded oral rehydration sachet free.
            $table->foreignUuid('bonus_product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->decimal('promo_price', 18, 4)->nullable();
            $table->decimal('discount_pct', 6, 3)->nullable();
            $table->decimal('max_free_per_order', 18, 4)->nullable();
            $table->boolean('repeat')->default(true); // may the buy/get ratio apply more than once per order?
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_lines');
    }
};
