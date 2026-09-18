<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('journal_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->unsignedInteger('line_number');
            $table->foreignUuid('account_id')->constrained('chart_of_accounts')->restrictOnDelete();
            $table->decimal('debit_amount', 18, 4)->default(0);
            $table->decimal('credit_amount', 18, 4)->default(0);
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('partner_type')->nullable(); // 'customer' | 'supplier'
            $table->uuid('partner_id')->nullable();
            $table->foreignUuid('tax_code_id')->nullable()->constrained('tax_codes')->nullOnDelete();
            $table->string('narration')->nullable();
        });

        // Part 12.3 / V6 20.11 — enforced at the database, not just in code:
        // a line is never both a debit and a credit.
        DB::statement('ALTER TABLE journal_entry_lines ADD CONSTRAINT chk_journal_entry_line_one_sided '
            .'CHECK (NOT (debit_amount > 0 AND credit_amount > 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
    }
};
