<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('code'); // VAT_STD, VAT_ZERO, EXEMPT, EXCISE, WHT, ...
            $table->string('name');
            $table->enum('tax_type', ['VAT', 'EXCISE', 'WITHHOLDING'])->default('VAT');
            $table->boolean('is_recoverable')->default(true);
            // gl_account_output_id/gl_account_input_id -> chart_of_accounts,
            // added once the Finance phase creates that table.
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organisation_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_codes');
    }
};
