<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('doc_number')->unique();
            $table->foreignUuid('from_store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignUuid('to_store_id')->constrained('stores')->cascadeOnDelete();
            // Part 7.6: DRAFT -> APPROVED -> DISPATCHED (IN_TRANSIT) -> RECEIVED, or DISCREPANCY.
            $table->enum('status', ['DRAFT', 'APPROVED', 'DISPATCHED', 'RECEIVED', 'DISCREPANCY'])->default('DRAFT');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
