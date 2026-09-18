<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Immutable — "the cheapest table in the database" the first time a
        // customer disputes a quoted price (Part 4.11). Retain 24 months.
        Schema::create('price_quote_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('quote_id')->unique();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->enum('sale_mode', ['RETAIL', 'WHOLESALE', 'DISPENSING']);
            $table->json('payload_json');
            $table->json('response_json');
            $table->dateTime('expires_at');
            $table->dateTime('created_at');

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_quote_logs');
    }
};
