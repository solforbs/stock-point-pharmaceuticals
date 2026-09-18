<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_breaks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_price_id')->constrained('product_prices')->cascadeOnDelete();
            $table->decimal('min_qty', 18, 4);
            $table->decimal('max_qty', 18, 4)->nullable(); // null = open-ended top tier
            $table->decimal('unit_price', 18, 4);
            // STEP (recommended, Part 4.4): the whole quantity prices at the
            // tier it lands in. MARGINAL: tariff-style, priced band by band.
            $table->enum('break_type', ['STEP', 'MARGINAL'])->default('STEP');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_breaks');
    }
};
