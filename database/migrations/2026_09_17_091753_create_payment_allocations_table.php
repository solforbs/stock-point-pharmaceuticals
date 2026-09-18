<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('allocated_to_type'); // 'sale' for now; 'supplier_invoice' etc. later
            $table->uuid('allocated_to_id');
            $table->decimal('amount', 18, 4);
            $table->timestamps();

            $table->index(['allocated_to_type', 'allocated_to_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
