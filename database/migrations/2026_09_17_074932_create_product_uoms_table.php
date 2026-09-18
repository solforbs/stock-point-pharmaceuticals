<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_uoms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('uom_id')->constrained('units_of_measure')->cascadeOnDelete();

            // Positive integer (Part 5.5) — enforced by the app layer (a CHECK
            // constraint would be ideal but factor_to_base's "must be an
            // integer" rule already rules out fractional units at the type
            // level; positivity is enforced by ProductUom's model validation).
            $table->unsignedBigInteger('factor_to_base');

            $table->boolean('is_base')->default(false);
            $table->boolean('is_purchase')->default(false);
            $table->boolean('is_sales')->default(false);
            $table->boolean('is_default_sales')->default(false);
            $table->string('barcode')->nullable()->unique();

            $table->timestamps();

            $table->unique(['product_id', 'uom_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_uoms');
    }
};
