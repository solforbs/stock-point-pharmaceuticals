<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The sales list (newest first) and the dashboard's "today" tiles read a
 * branch's sales by date without a sale mode: the existing index starts
 * with sale_mode after branch_id, so both sorted every sale in the branch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->index(['branch_id', 'posted_at'], 'sales_branch_posted_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_branch_posted_at_index');
        });
    }
};
