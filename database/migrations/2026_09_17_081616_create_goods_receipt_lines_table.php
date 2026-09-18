<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipt_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->foreignUuid('purchase_order_line_id')->nullable()->constrained('purchase_order_lines')->nullOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('uom_id')->constrained('units_of_measure')->cascadeOnDelete();

            $table->decimal('qty_ordered', 18, 4)->default(0); // snapshot from the PO line
            $table->decimal('qty_delivered', 18, 4);
            $table->decimal('qty_accepted', 18, 4)->default(0);
            $table->decimal('qty_rejected', 18, 4)->default(0);
            $table->text('rejection_reason')->nullable();

            // Mandatory — creates the batch (Part 9.2).
            $table->string('batch_number');
            $table->date('expiry_date');
            $table->date('manufacture_date')->nullable();
            $table->foreignUuid('batch_id')->nullable()->constrained('product_batches')->nullOnDelete();

            $table->decimal('unit_cost', 18, 4);
            $table->decimal('landed_unit_cost', 18, 4)->nullable();
            $table->decimal('temperature_on_arrival', 5, 2)->nullable();
            $table->boolean('coa_received')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_lines');
    }
};
