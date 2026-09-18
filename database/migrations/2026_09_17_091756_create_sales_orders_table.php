<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Stage 2 — confirming an order is the moment stock gets FEFO-reserved
        // (batch-committed, via stock_reservations) and credit gets checked.
        // No ledger or journal entry yet: that only happens at dispatch.
        Schema::create('sales_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->enum('sale_mode', ['RETAIL', 'WHOLESALE', 'DISPENSING'])->default('WHOLESALE');
            $table->string('sub_type')->nullable();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->uuid('quotation_id')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('doc_number')->unique();
            $table->enum('status', ['DRAFT', 'CONFIRMED', 'IN_PROGRESS', 'PARTIALLY_FULFILLED', 'FULFILLED', 'CANCELLED'])->default('DRAFT');
            $table->date('required_date')->nullable();

            $table->decimal('subtotal', 18, 4)->default(0);
            $table->decimal('discount_total', 18, 4)->default(0);
            $table->decimal('tax_total', 18, 4)->default(0);
            $table->decimal('grand_total', 18, 4)->default(0);
            $table->decimal('cost_total', 18, 4)->default(0);

            $table->string('idempotency_key')->unique();

            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_orders');
    }
};
