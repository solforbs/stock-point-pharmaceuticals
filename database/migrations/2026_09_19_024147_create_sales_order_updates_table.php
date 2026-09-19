<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 7 — what the customer has been told about their order, and when.
 *
 * Kept as a record rather than only sent: a customer asking "nobody told me
 * it was cancelled" can be answered, and the same milestone is never
 * announced twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_order_updates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('sales_order_id')->constrained('sales_orders')->cascadeOnDelete();
            $table->string('milestone', 30);
            $table->string('channel', 20)->default('EMAIL');
            $table->string('recipient', 190)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['sales_order_id', 'milestone']);
            $table->index('sent_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_order_updates');
    }
};
