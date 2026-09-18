<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only (Part 19.1): no updated_at, and the application DB user
        // is granted INSERT/SELECT only — never UPDATE or DELETE — on this table.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestamp('occurred_at');

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('username_snapshot')->nullable();

            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('terminal_id')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();

            // Machine code: SALE_POSTED, PRICE_OVERRIDDEN, FEFO_OVERRIDDEN, STOCK_ADJUSTED, PERMISSION_GRANTED, ...
            $table->string('action');
            $table->string('entity_type');
            $table->string('entity_id');
            $table->string('reference')->nullable();

            $table->json('before_json')->nullable();
            $table->json('after_json')->nullable();
            $table->json('changed_fields')->nullable();
            $table->text('reason')->nullable();
            $table->string('approval_id')->nullable();
            $table->string('request_id')->nullable();

            $table->index(['entity_type', 'entity_id']);
            $table->index('occurred_at');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
