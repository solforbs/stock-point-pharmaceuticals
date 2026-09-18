<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landed_cost_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('landed_cost_id')->constrained('landed_costs')->cascadeOnDelete();
            $table->foreignUuid('goods_receipt_line_id')->constrained('goods_receipt_lines')->cascadeOnDelete();
            $table->decimal('allocated_amount', 18, 4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landed_cost_allocations');
    }
};
