<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per product (Part 4.11).
        Schema::create('product_discount_policies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->unique()->constrained('products')->cascadeOnDelete();
            $table->boolean('discount_allowed')->default(true);
            $table->decimal('max_discount_pct', 6, 3)->default(0);
            $table->decimal('max_discount_amount', 18, 4)->nullable();
            $table->decimal('min_margin_pct', 6, 3)->default(0);
            $table->boolean('bonus_allowed')->default(true);
            $table->boolean('promo_stackable')->default(false);
            $table->decimal('discount_approval_pct', 6, 3)->nullable(); // above this needs approval
            $table->enum('round_to', ['NONE', 'WHOLE', 'FIVE_CENTS', 'TEN_CENTS'])->default('NONE');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_discount_policies');
    }
};
