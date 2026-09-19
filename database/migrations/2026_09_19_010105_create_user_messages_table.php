<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Part 17 — messages between people using the system: "counter 2 needs a
 * price override", "the cold room alarm is going". They are delivered over
 * the websocket the moment they are sent, and kept here so a message is
 * still there when the recipient logs in tomorrow.
 *
 * A message is never deleted by its recipient, only marked read, so "I never
 * got it" can always be checked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            /** Null means everyone in the branch. */
            $table->foreignId('recipient_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('subject', 150);
            $table->text('body');
            $table->enum('priority', ['NORMAL', 'HIGH', 'URGENT'])->default('NORMAL');
            $table->string('link')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_id', 'read_at']);
            $table->index(['branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_messages');
    }
};
