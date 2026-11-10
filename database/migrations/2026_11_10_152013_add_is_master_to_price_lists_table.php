<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The master price list: one list per institution, built from the stock on
 * hand, whose prices the POS uses ahead of every tier, mode and branch list.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('price_lists', function (Blueprint $table) {
            $table->boolean('is_master')->default(false)->after('priority');
        });
    }

    public function down(): void
    {
        Schema::table('price_lists', function (Blueprint $table) {
            $table->dropColumn('is_master');
        });
    }
};
