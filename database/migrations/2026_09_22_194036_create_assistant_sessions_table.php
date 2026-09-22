<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The assistant's sign-in by emailed code. One row is the whole life of an
 * attempt: the code that was sent, the tries against it, and — once it
 * matches — the read-only session token that answers commands.
 *
 * Nothing reusable is stored in the clear: the code and the token are kept
 * as SHA-256 digests, exactly as the one-time invitation links are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assistant_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // The address as typed. There may be no user behind it: an
            // unknown address still gets a row so the reply and the timing
            // look the same either way.
            $table->string('email');
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->char('code_hash', 64);
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('code_expires_at');
            $table->timestamp('verified_at')->nullable();
            $table->char('token_hash', 64)->nullable()->unique();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->unsignedSmallInteger('queries')->default(0);
            $table->timestamps();

            $table->index(['email', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_sessions');
    }
};
