<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stage 4/5 — dispatch is the actual stock + financial posting event
        // (mirrors CheckoutService's atomic Sale creation, but against
        // already-reserved batches instead of a fresh FEFO run). Delivery
        // confirmation afterwards is proof-of-receipt only — no further
        // financial impact, consistent with "one posting event per document."
        Schema::create('delivery_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUuid('sales_order_id')->constrained('sales_orders')->restrictOnDelete();
            $table->foreignUuid('picking_list_id')->constrained('picking_lists')->restrictOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('doc_number')->unique();
            $table->enum('status', ['DRAFT', 'DISPATCHED', 'DELIVERED', 'CANCELLED'])->default('DRAFT');

            $table->string('vehicle_reg')->nullable();
            $table->string('driver_name')->nullable();
            $table->string('driver_phone')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('received_by_name')->nullable();

            $table->uuid('sale_id')->nullable();
            $table->string('idempotency_key')->unique();

            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_notes');
    }
};
