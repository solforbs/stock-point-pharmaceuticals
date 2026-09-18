<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 8.4 — the score-10 capability. A recall must answer within minutes
 * where the affected stock is and who received it, then track recovery
 * to an effectiveness percentage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recalls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('doc_number')->unique();
            $table->enum('status', ['INITIATED', 'SCOPED', 'BLOCKED', 'NOTIFIED', 'RECOVERING', 'RECONCILED', 'DISPOSITIONED', 'CLOSED'])->default('INITIATED')->index();
            $table->enum('source', ['MANUFACTURER', 'PPB', 'INTERNAL']);
            $table->string('external_reference')->nullable();
            $table->text('reason');
            $table->enum('disposition', ['RETURN_TO_SUPPLIER', 'DESTROY'])->nullable();
            $table->decimal('effectiveness_pct', 8, 2)->nullable();
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('initiated_at');
            $table->timestamp('blocked_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('recall_batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('recall_id')->constrained('recalls')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('batch_id')->constrained('product_batches')->cascadeOnDelete();
            $table->string('status_before')->nullable();
            $table->decimal('on_hand_at_scope', 18, 4)->default(0);
            $table->decimal('in_transit_at_scope', 18, 4)->default(0);
            $table->decimal('distributed_qty', 18, 4)->default(0);
            $table->decimal('recovered_qty', 18, 4)->default(0);
            $table->decimal('disposed_qty', 18, 4)->default(0);
            $table->timestamps();
            $table->unique(['recall_id', 'batch_id']);
        });

        Schema::create('recall_customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('recall_id')->constrained('recalls')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('customer_name_snapshot');
            $table->string('contact_snapshot')->nullable();
            $table->decimal('qty_distributed', 18, 4)->default(0);
            $table->decimal('qty_recovered', 18, 4)->default(0);
            $table->timestamp('notified_at')->nullable();
            $table->string('notification_reference')->nullable();
            $table->timestamps();
        });

        // Part 11.2 — supplier returns become postable documents (reverse of a
        // GRN) and can be the disposition of a recall.
        Schema::table('supplier_returns', function (Blueprint $table) {
            $table->uuid('recall_id')->nullable()->index()->after('reason');
            $table->decimal('total_value', 18, 4)->default(0)->after('recall_id');
            $table->foreignId('posted_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable()->after('posted_by');
        });

        Schema::table('waste_disposals', function (Blueprint $table) {
            // false when the goods never re-entered stock (a customer return
            // dispositioned DESTROY): the record exists, no ledger row does.
            $table->boolean('stock_effect')->default(true)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('waste_disposals', fn (Blueprint $table) => $table->dropColumn('stock_effect'));
        Schema::table('supplier_returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('posted_by');
            $table->dropColumn(['recall_id', 'total_value', 'posted_at']);
        });
        Schema::dropIfExists('recall_customers');
        Schema::dropIfExists('recall_batches');
        Schema::dropIfExists('recalls');
    }
};
