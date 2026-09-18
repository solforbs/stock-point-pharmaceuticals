<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('landed_costs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('goods_receipt_id')->constrained('goods_receipts')->cascadeOnDelete();
            $table->decimal('freight', 18, 4)->default(0);
            $table->decimal('clearing', 18, 4)->default(0);
            $table->decimal('insurance', 18, 4)->default(0);
            $table->decimal('duty', 18, 4)->default(0);
            $table->decimal('supplier_rebate', 18, 4)->default(0);
            $table->enum('allocation_basis', ['VALUE', 'WEIGHT', 'VOLUME', 'QUANTITY'])->default('VALUE');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landed_costs');
    }
};
