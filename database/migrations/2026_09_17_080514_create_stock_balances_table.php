<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Derived, reconcilable cache (Part 7.1): qty_on_hand must always
        // equal SUM(stock_ledgers.qty_base) for the same triple. A surrogate
        // UUID PK is used instead of the literal (product,batch,store)
        // composite the blueprint names, because batch_id is nullable for
        // non-batch-tracked products and MySQL allows duplicate NULLs in a
        // composite primary key's non-null parts — the unique index below
        // still enforces the one-row-per-triple rule for batched products.
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUuid('batch_id')->nullable()->constrained('product_batches')->cascadeOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->cascadeOnDelete();

            $table->decimal('qty_on_hand', 18, 4)->default(0);
            $table->decimal('qty_reserved', 18, 4)->default(0);
            $table->decimal('qty_quarantined', 18, 4)->default(0);
            $table->decimal('wac', 18, 4)->default(0); // weighted average cost

            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'batch_id', 'store_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};
