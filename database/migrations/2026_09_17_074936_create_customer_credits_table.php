<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // V6: current-state table (one row per customer), not a history log.
        // current_balance/unallocated_receipts are maintained by the AR
        // posting routines built in the Finance phase; limit/hold changes
        // are still audited via audit_logs (Part 19.2), just not versioned
        // in this table itself.
        Schema::create('customer_credits', function (Blueprint $table) {
            $table->foreignUuid('customer_id')->primary()->constrained('customers')->cascadeOnDelete();
            $table->decimal('credit_limit', 18, 4)->default(0);
            $table->decimal('current_balance', 18, 4)->default(0);
            $table->decimal('unallocated_receipts', 18, 4)->default(0);
            $table->boolean('on_hold')->default(false);
            $table->string('hold_reason')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_credits');
    }
};
