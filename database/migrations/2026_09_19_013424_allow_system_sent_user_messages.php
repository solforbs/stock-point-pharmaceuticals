<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 17 — the system needs to speak too. A notification raised by the
 * application ("this requisition needs your approval") has no human sender,
 * and carries the kind of event it came from so a screen can group them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_messages', function (Blueprint $table) {
            $table->foreignId('sender_id')->nullable()->change();
            $table->string('category', 40)->nullable()->after('priority');
            $table->index(['branch_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::table('user_messages', function (Blueprint $table) {
            $table->dropIndex(['branch_id', 'category']);
            $table->dropColumn('category');
        });
    }
};
