<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 11.3 — pharmaceutical waste has a regulated disposal path: two
 * witnesses, a disposal certificate, a PPB reference where applicable, and
 * a value written off to a dedicated expense account.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waste_disposals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->uuid('recall_id')->nullable()->index();
            $table->string('doc_number')->unique();
            $table->enum('status', ['DRAFT', 'POSTED'])->default('DRAFT');
            $table->enum('reason', ['EXPIRED', 'DAMAGED', 'RECALLED', 'EXCURSION', 'CONTAMINATED'])->index();
            $table->string('disposal_method')->nullable();
            $table->string('disposal_contractor')->nullable();
            $table->string('certificate_reference')->nullable();
            $table->string('ppb_reference')->nullable();
            $table->foreignId('witnessed_by_1')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('witnessed_by_2')->nullable()->constrained('users')->nullOnDelete();
            $table->json('photos_json')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_value', 18, 4)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('waste_disposal_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('waste_disposal_id')->constrained('waste_disposals')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('batch_id')->constrained('product_batches')->cascadeOnDelete();
            $table->decimal('qty_base', 18, 4);
            $table->decimal('unit_cost', 18, 4);
            $table->decimal('line_value', 18, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waste_disposal_lines');
        Schema::dropIfExists('waste_disposals');
    }
};
