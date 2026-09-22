<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The training centre (client item 17): what each person has read and
 * practised, how they scored on each knowledge check, and what they said
 * was unclear. The curriculum itself (lessons, tasks, questions and their
 * answers) lives in code, App\Services\Training\TrainingCatalogue, so it
 * ships with the release that changes the screens it describes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('training_progress', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('module_key', 40);
            $table->enum('item_type', ['LESSON', 'TASK']);
            $table->string('item_key', 60);
            // Nullable only because MariaDB refuses a second TIMESTAMP with no default.
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            /** A task the system confirmed from the records the trainee created. */
            $table->boolean('is_verified')->default(false);
            $table->string('verification_detail', 255)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'module_key', 'item_type', 'item_key'], 'training_progress_item_unique');
            $table->index(['organisation_id', 'module_key']);
        });

        Schema::create('training_quiz_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('module_key', 40);
            $table->unsignedTinyInteger('correct');
            $table->unsignedTinyInteger('total');
            $table->unsignedTinyInteger('score_pct');
            $table->boolean('passed');
            /** The options chosen, question id => option index, kept for review. */
            $table->json('answers');
            $table->timestamps();

            $table->index(['user_id', 'module_key']);
            $table->index(['organisation_id', 'module_key']);
        });

        Schema::create('training_feedback', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('module_key', 40);
            $table->unsignedTinyInteger('rating');
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'module_key']);
            $table->index(['organisation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_feedback');
        Schema::dropIfExists('training_quiz_attempts');
        Schema::dropIfExists('training_progress');
    }
};
