<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Part 10.6 — packing sits between pick and dispatch: how many packages
     * the picked goods went into, their weight and any packer's notes.
     */
    public function up(): void
    {
        Schema::table('picking_lists', function (Blueprint $table) {
            $table->timestamp('packed_at')->nullable()->after('completed_at');
            $table->foreignId('packed_by')->nullable()->after('packed_at')->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('package_count')->nullable()->after('packed_by');
            $table->decimal('total_weight_kg', 10, 3)->nullable()->after('package_count');
            $table->text('packing_notes')->nullable()->after('total_weight_kg');
        });
    }

    public function down(): void
    {
        Schema::table('picking_lists', function (Blueprint $table) {
            $table->dropConstrainedForeignId('packed_by');
            $table->dropColumn(['packed_at', 'package_count', 'total_weight_kg', 'packing_notes']);
        });
    }
};
