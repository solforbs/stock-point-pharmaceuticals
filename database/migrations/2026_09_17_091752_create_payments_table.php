<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignUuid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->enum('method', ['CASH', 'MPESA', 'BANK', 'CARD', 'CHEQUE'])->default('CASH');
            $table->string('reference')->nullable(); // M-PESA code, cheque number, ...
            $table->decimal('amount', 18, 4);
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('received_at')->useCurrent();
            $table->timestamp('reconciled_at')->nullable();

            // Part 10.6 / Phase 8 — a payment is never deleted, only reversed
            // (a bounced cheque, a bank error): a compensating journal entry
            // and AR adjustment are posted, this row just records the fact.
            $table->enum('status', ['CLEARED', 'REVERSED'])->default('CLEARED');
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamp('voided_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
