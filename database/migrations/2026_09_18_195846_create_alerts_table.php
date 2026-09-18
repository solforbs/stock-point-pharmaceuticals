<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 17 — standing alerts: money falling due and stock running out of
 * shelf life. A nightly scan keeps this table equal to what is true right
 * now, so an alert that has been paid or sold disappears by itself and is
 * never a stale to-do. `alert_key` is what makes a re-scan an update
 * rather than a duplicate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('alert_key');
            $table->string('category', 30); // RECEIVABLE | PAYABLE | EXPIRY
            $table->string('type', 40);     // e.g. INVOICE_OVERDUE, BATCH_EXPIRING
            $table->enum('severity', ['INFO', 'WARNING', 'CRITICAL'])->default('INFO');
            $table->string('title');
            $table->string('detail', 500)->nullable();
            $table->string('entity_type', 40)->nullable();
            $table->string('entity_id', 64)->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('amount', 18, 4)->nullable();
            $table->string('link')->nullable();
            /** The permission a user must hold to be shown this alert. */
            $table->string('permission', 60);
            // Nullable only because MariaDB refuses a second TIMESTAMP with no
            // default; the scanner always writes both.
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'alert_key']);
            $table->index(['branch_id', 'resolved_at', 'severity']);
            $table->index(['branch_id', 'category', 'resolved_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};
