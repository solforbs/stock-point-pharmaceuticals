<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            // V6: the same engine handles retail, wholesale, and dispensing —
            // this column, not a code branch, is the only difference.
            $table->enum('sale_mode', ['RETAIL', 'WHOLESALE', 'DISPENSING']);
            $table->string('sub_type')->nullable(); // OTC, DISPENSING, WHOLESALE, QUOTATION_CONVERTED
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->uuid('quote_id')->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('terminal_id')->nullable();
            $table->string('doc_number')->unique();
            $table->enum('status', ['DRAFT', 'POSTED', 'VOIDED'])->default('POSTED');

            $table->decimal('subtotal', 18, 4);
            $table->decimal('discount_total', 18, 4)->default(0);
            $table->decimal('tax_total', 18, 4)->default(0);
            $table->decimal('grand_total', 18, 4);
            $table->decimal('cost_total', 18, 4);

            $table->string('idempotency_key')->unique();

            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamp('voided_at')->nullable();

            // Kenya eTIMS (Part 13.6) — left nullable/unused until the Tax
            // Centre phase actually integrates with the KRA gateway.
            $table->string('etims_status')->nullable();
            $table->string('etims_control_code')->nullable();
            $table->string('etims_invoice_number')->nullable();
            $table->timestamp('etims_submitted_at')->nullable();
            $table->string('etims_error')->nullable();

            $table->timestamp('posted_at')->useCurrent();

            $table->index(['branch_id', 'sale_mode', 'posted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
