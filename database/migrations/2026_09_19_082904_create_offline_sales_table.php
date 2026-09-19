<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 16.7 / 17.5 — a sale rung up at the counter while the server was
 * unreachable, as the terminal handed it over on reconnect.
 *
 * Kept whether or not it could be posted: the goods have already left the
 * shop and the money is already in the till, so a sale that cannot post
 * (stock gone, store closed) waits here as a CONFLICT for a supervisor
 * rather than being lost on the device.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offline_sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();
            $table->string('terminal_id', 50)->nullable();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('idempotency_key', 100)->unique();
            // dateTime, not timestamp: MySQL can silently give a non-null timestamp
            // ON UPDATE CURRENT_TIMESTAMP, which would overwrite when the sale was
            // made every time its status changes.
            $table->dateTime('sold_at');
            $table->json('payload_json');
            $table->decimal('offline_total', 18, 4);
            $table->decimal('server_total', 18, 4)->nullable();
            $table->decimal('price_variance', 18, 4)->default(0);
            $table->string('status', 20);
            $table->foreignUuid('sale_id')->nullable()->constrained('sales')->nullOnDelete();
            $table->string('error_code', 50)->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offline_sales');
    }
};
