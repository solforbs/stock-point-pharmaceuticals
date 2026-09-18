<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_note_line_batch_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Explicit short FK name: the auto-generated one exceeds MySQL's
            // 64-character identifier limit for this long table/column pair.
            $table->uuid('delivery_note_line_id');
            $table->foreign('delivery_note_line_id', 'dnlba_dn_line_id_fk')
                ->references('id')->on('delivery_note_lines')->cascadeOnDelete();
            $table->foreignUuid('batch_id')->constrained('product_batches')->restrictOnDelete();
            $table->foreignUuid('store_id')->constrained('stores')->restrictOnDelete();
            $table->decimal('qty_base', 18, 4);
            $table->decimal('unit_cost', 18, 4);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_note_line_batch_allocations');
    }
};
