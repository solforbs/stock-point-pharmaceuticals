<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->nullable()->constrained('organisations')->cascadeOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained('branches')->cascadeOnDelete();
            // scope groups related keys, e.g. "procurement", "pricing" — the
            // most branch-specific row with a value wins over the org-wide one.
            $table->string('scope');
            $table->string('key');
            $table->json('value_json');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('set_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('set_at')->useCurrent();

            $table->unique(['organisation_id', 'branch_id', 'scope', 'key', 'effective_from'], 'settings_unique_row');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
