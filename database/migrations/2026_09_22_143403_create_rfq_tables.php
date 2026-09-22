<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supplier quotes and the competitive bid analysis (CBA). A request for
 * quotation lists what is needed and which suppliers are asked; each
 * supplier's quote is recorded line by line; the analysis scores them and
 * the award turns the chosen quotes into draft purchase orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rfqs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('doc_number');
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('title', 150);
            $table->date('needed_by')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['DRAFT', 'SENT', 'CLOSED', 'AWARDED', 'CANCELLED'])->default('DRAFT');
            // Score weights in percent; they are adjustable per request.
            $table->decimal('weight_price', 5, 2)->default(60);
            $table->decimal('weight_lead_time', 5, 2)->default(15);
            $table->decimal('weight_payment_terms', 5, 2)->default(10);
            $table->decimal('weight_supplier_record', 5, 2)->default(15);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            // The award: who decided, when, and why they departed from the recommendation.
            $table->enum('award_mode', ['SINGLE', 'SPLIT'])->nullable();
            $table->boolean('award_followed_recommendation')->nullable();
            $table->text('award_justification')->nullable();
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('awarded_at')->nullable();
            // The analysis exactly as the approver saw it, for the award summary.
            $table->json('analysis_snapshot')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'doc_number']);
            $table->index(['branch_id', 'status']);
        });

        Schema::create('rfq_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('uom_id')->constrained('units_of_measure');
            $table->decimal('qty', 18, 4);
            $table->string('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignUuid('awarded_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignUuid('recommended_supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->timestamps();
        });

        // One row per invited supplier; the quote header lives here once received.
        Schema::create('rfq_suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->foreignUuid('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->enum('quote_status', ['AWAITING', 'RECEIVED', 'DECLINED'])->default('AWAITING');
            $table->string('quote_reference', 100)->nullable();
            $table->date('quote_date')->nullable();
            $table->date('valid_until')->nullable();
            $table->unsignedSmallInteger('payment_terms_days')->nullable();
            $table->decimal('delivery_charge', 18, 4)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('entered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();

            $table->unique(['rfq_id', 'supplier_id']);
        });

        // A line the supplier did not quote simply has no row here.
        Schema::create('rfq_quote_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rfq_supplier_id')->constrained('rfq_suppliers')->cascadeOnDelete();
            $table->foreignUuid('rfq_line_id')->constrained('rfq_lines')->cascadeOnDelete();
            $table->decimal('unit_price', 18, 4);
            $table->decimal('qty_available', 18, 4)->nullable(); // NULL = the full quantity
            $table->unsignedSmallInteger('lead_time_days');
            $table->unsignedSmallInteger('shelf_life_months')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['rfq_supplier_id', 'rfq_line_id']);
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->foreignUuid('rfq_id')->nullable()->after('requisition_id')->constrained('rfqs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rfq_id');
        });
        Schema::dropIfExists('rfq_quote_lines');
        Schema::dropIfExists('rfq_suppliers');
        Schema::dropIfExists('rfq_lines');
        Schema::dropIfExists('rfqs');
    }
};
