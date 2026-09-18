<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('code');
            $table->string('sku')->nullable();
            $table->string('gtin')->nullable(); // global trade item number / barcode
            $table->string('name');
            $table->string('generic_name')->nullable();
            $table->string('strength')->nullable(); // e.g. "500mg"

            $table->foreignUuid('dosage_form_id')->nullable()->constrained('dosage_forms')->nullOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            $table->foreignUuid('manufacturer_id')->nullable()->constrained('manufacturers')->nullOnDelete();
            // base_uom_id references units_of_measure, but the specific
            // conversion factor for THIS product lives in product_uoms —
            // this column just names which UOM row is the base for quick lookup.
            $table->foreignUuid('base_uom_id')->nullable()->constrained('units_of_measure')->nullOnDelete();
            $table->foreignUuid('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
            $table->foreignUuid('storage_condition_id')->nullable()->constrained('storage_conditions')->nullOnDelete();

            // Part 5.5: DECIMAL storage always, but discrete products must
            // only ever hold whole-number quantities (enforced in app logic).
            $table->boolean('is_discrete')->default(true);
            // Part 5.7: a sealed pack can't be split across batches when true.
            // requires_prescription/is_controlled moved to the `drugs` table
            // (V6 Drug Master) — not every product is a medicine.
            $table->boolean('pack_integrity')->default(true);
            $table->boolean('requires_batch')->default(true);

            $table->decimal('reorder_point', 18, 4)->default(0);
            $table->decimal('safety_stock', 18, 4)->default(0);
            $table->unsignedSmallInteger('lead_time_days')->default(0);
            $table->decimal('default_price', 18, 4)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->unique(['organisation_id', 'code']);
            $table->unique('sku');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
