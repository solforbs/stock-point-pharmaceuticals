<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customer communication log — calls, visits and messages with an
     * optional follow-up date that shows up until it is marked done.
     */
    public function up(): void
    {
        Schema::create('customer_interactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignUuid('contact_id')->nullable()->constrained('customer_contacts')->nullOnDelete();
            $table->enum('channel', ['CALL', 'VISIT', 'EMAIL', 'SMS', 'WHATSAPP', 'OTHER']);
            $table->text('summary');
            $table->date('follow_up_date')->nullable();
            $table->boolean('follow_up_done')->default(false);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['customer_id', 'occurred_at']);
            $table->index(['follow_up_done', 'follow_up_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_interactions');
    }
};
