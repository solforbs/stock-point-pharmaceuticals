<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 8.5 — cold chain. Every temperature reading is kept; a reading outside
 * the store's window opens (or extends) an excursion that stays OPEN until a
 * pharmacist has assessed its impact and closed it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cold_chain_excursions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->decimal('min_temp', 5, 2);
            $table->decimal('max_temp', 5, 2);
            $table->decimal('range_min', 5, 2);
            $table->decimal('range_max', 5, 2);
            $table->enum('status', ['OPEN', 'UNDER_REVIEW', 'CLOSED'])->default('OPEN');
            $table->text('impact_assessment')->nullable();
            $table->enum('action_taken', ['NO_IMPACT', 'STOCK_QUARANTINED', 'STOCK_DISPOSED'])->nullable();
            $table->json('affected_batch_ids')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('review_started_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->index(['store_id', 'status']);
        });

        Schema::create('cold_chain_readings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->decimal('temperature_c', 5, 2);
            $table->decimal('humidity_pct', 5, 2)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('source', ['MANUAL', 'LOGGER'])->default('MANUAL');
            $table->string('note', 500)->nullable();
            $table->boolean('is_excursion')->default(false);
            $table->foreignUuid('excursion_id')->nullable()->constrained('cold_chain_excursions')->nullOnDelete();
            $table->timestamps();
            $table->index(['store_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cold_chain_readings');
        Schema::dropIfExists('cold_chain_excursions');
    }
};
