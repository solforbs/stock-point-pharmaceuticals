<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_reservations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('batch_id')->nullable()->constrained('product_batches')->nullOnDelete(); // nullable until picking
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->decimal('qty_base', 18, 4);
            $table->string('source_doc_type');
            $table->uuid('source_doc_id');
            $table->enum('status', ['ACTIVE', 'CONSUMED', 'RELEASED', 'EXPIRED'])->default('ACTIVE');
            $table->timestamp('expires_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'store_id', 'status']);
            $table->index(['source_doc_type', 'source_doc_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_reservations');
    }
};
