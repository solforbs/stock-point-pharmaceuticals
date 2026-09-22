<?php

namespace Tests\Feature\Api;

use App\Models\Organisation;
use App\Models\TrainingFeedback;
use App\Models\TrainingProgress;
use App\Models\User;
use App\Models\UserMessage;
use App\Services\Tenancy\TenantContext;
use App\Services\Training\TrainingCatalogue;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Client item 17 — the training centre: lessons, practice tasks checked
 * against the books, knowledge checks scored on the server, feedback, and a
 * manager's view of everyone's progress within their own institution.
 */
class TrainingHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    private User $colleague;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->colleague = $this->colleague([
            'name' => 'Counter Two', 'username' => 'counter2', 'email' => 'counter2@stockpoint.test',
            'password' => Hash::make('a-long-enough-password'),
        ]);
        $this->grantPermissions(['sale.view']);
        Sanctum::actingAs($this->user);
    }

    public function test_the_catalogue_and_a_module_never_carry_the_answers_or_verification_rules(): void
    {
        $overview = $this->getJson('/api/training')->assertOk()
            ->assertJsonPath('pass_mark', 70)
            ->assertJsonPath('can_manage', false)
            ->assertJsonCount(count(TrainingCatalogue::modules()), 'modules')
            ->assertJsonPath('modules.0.key', 'getting-started')
            ->assertJsonPath('modules.0.recommended', true);
        $this->assertStringNotContainsString('"answer"', (string) $overview->getContent());

        $module = $this->getJson('/api/training/modules/cashier-pos')->assertOk();
        $body = (string) $module->getContent();
        $this->assertStringNotContainsString('"answer"', $body);
        $this->assertStringNotContainsString('"verify"', $body);
        $this->assertStringNotContainsString('user_column', $body);
        $this->assertNotEmpty($module->json('module.quiz'));
        $this->assertSame(['id', 'question', 'options'], array_keys($module->json('module.quiz.0')));
        $this->assertTrue($module->json('module.tasks.0.auto_verified'));

        $this->getJson('/api/training/modules/no-such-module')->assertNotFound();
    }

    public function test_the_knowledge_check_is_scored_on_the_server_and_the_best_score_is_kept(): void
    {
        $quiz = TrainingCatalogue::module('getting-started')['quiz'];
        $allRight = collect($quiz)->mapWithKeys(fn (array $q) => [$q['id'] => $q['answer']])->all();
        $allWrong = collect($quiz)->mapWithKeys(fn (array $q) => [$q['id'] => ($q['answer'] + 1) % count($q['options'])])->all();

        $failed = $this->postJson('/api/training/modules/getting-started/quiz', ['answers' => $allWrong])->assertCreated()
            ->assertJsonPath('attempt.score_pct', 0)
            ->assertJsonPath('attempt.passed', false)
            ->assertJsonCount(count($quiz), 'review');
        $this->assertStringNotContainsString('"answer"', (string) $failed->getContent());
        $this->assertSame($quiz[0]['lesson'], $failed->json('review.0.lesson'), 'a wrong answer points back to its lesson');

        // One wrong out of six is 83%, a pass; unanswered questions count as wrong.
        $oneWrong = $allRight;
        unset($oneWrong[$quiz[0]['id']]);
        $this->postJson('/api/training/modules/getting-started/quiz', ['answers' => $oneWrong])->assertCreated()
            ->assertJsonPath('attempt.score_pct', (int) floor((count($quiz) - 1) * 100 / count($quiz)))
            ->assertJsonPath('attempt.passed', true)
            ->assertJsonPath('review.0.question_id', $quiz[0]['id']);

        $this->postJson('/api/training/modules/getting-started/quiz', ['answers' => $allRight])->assertCreated()
            ->assertJsonPath('attempt.score_pct', 100)
            ->assertJsonPath('best_score', 100);

        // A worse retake never lowers the best score.
        $this->postJson('/api/training/modules/getting-started/quiz', ['answers' => $allWrong])->assertCreated()
            ->assertJsonPath('best_score', 100);

        $this->getJson('/api/training/modules/getting-started')->assertOk()
            ->assertJsonPath('progress.attempts', 4)
            ->assertJsonPath('progress.best_score', 100)
            ->assertJsonPath('progress.passed', true)
            ->assertJsonCount(4, 'attempts')
            ->assertJsonMissingPath('attempts.0.answers');
    }

    public function test_lessons_tasks_quiz_and_feedback_add_up_to_a_completed_module_with_a_certificate(): void
    {
        $module = TrainingCatalogue::module('getting-started');

        $this->postJson('/api/training/modules/getting-started/lessons/sign-in/viewed')->assertOk();
        $this->postJson('/api/training/modules/getting-started/lessons/sign-in/viewed')->assertOk();
        $this->postJson('/api/training/modules/getting-started/lessons/nope/viewed')->assertNotFound();
        $this->getJson('/api/training/modules/getting-started')->assertOk()
            ->assertJsonPath('progress.lessons_viewed', 1)
            ->assertJsonPath('progress.completed', false);

        $this->get("/api/training/certificates/{$this->user->id}/getting-started")->assertStatus(422);

        foreach ($module['lessons'] as $lesson) {
            $this->postJson("/api/training/modules/getting-started/lessons/{$lesson['key']}/viewed")->assertOk();
        }
        foreach ($module['tasks'] as $task) {
            $this->postJson("/api/training/modules/getting-started/tasks/{$task['key']}/complete", ['note' => 'Done with my supervisor.'])
                ->assertOk()->assertJsonPath('note', 'Done with my supervisor.');
        }
        $answers = collect($module['quiz'])->mapWithKeys(fn (array $q) => [$q['id'] => $q['answer']])->all();
        $this->postJson('/api/training/modules/getting-started/quiz', ['answers' => $answers])->assertCreated();

        $this->postJson('/api/training/modules/getting-started/feedback', ['rating' => 6])->assertStatus(422);
        $this->postJson('/api/training/modules/getting-started/feedback', ['rating' => 4, 'comments' => 'The branch switch was unclear.'])
            ->assertOk()->assertJsonPath('rating', 4);
        $this->postJson('/api/training/modules/getting-started/feedback', ['rating' => 5])->assertOk();
        $this->assertSame(1, TrainingFeedback::where('user_id', $this->user->id)->count(), 'sending feedback again replaces it');

        $this->getJson('/api/training')->assertOk()
            ->assertJsonPath('modules.0.progress.completed', true)
            ->assertJsonPath('modules.0.progress.feedback_rating', 5)
            ->assertJsonPath('modules.0.progress.lessons_viewed', count($module['lessons']))
            ->assertJsonPath('modules.0.progress.tasks_completed', count($module['tasks']));

        $pdf = $this->get("/api/training/certificates/{$this->user->id}/getting-started")->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', (string) $pdf->getContent());
    }

    public function test_a_task_is_verified_only_by_a_record_the_trainee_created_after_starting_it(): void
    {
        // A message sent before the task started proves nothing.
        $this->sendMessage($this->user, now()->subHour());
        $this->postJson('/api/training/modules/getting-started/tasks/message/start')->assertOk()
            ->assertJsonPath('completed_at', null);
        $this->postJson('/api/training/modules/getting-started/tasks/message/complete')->assertOk()
            ->assertJsonPath('is_verified', false)
            ->assertJsonPath('verification_detail', fn (string $v) => str_starts_with($v, 'Self-confirmed'));

        // Nor does a colleague's message.
        $this->postJson('/api/training/modules/getting-started/tasks/message/start')->assertOk();
        $this->sendMessage($this->colleague, now()->addSeconds(5));
        $this->postJson('/api/training/modules/getting-started/tasks/message/complete')->assertOk()
            ->assertJsonPath('is_verified', false);

        // The trainee's own message, sent after starting, does.
        $this->postJson('/api/training/modules/getting-started/tasks/message/start')->assertOk();
        $this->sendMessage($this->user, now()->addSeconds(5));
        $this->postJson('/api/training/modules/getting-started/tasks/message/complete')->assertOk()
            ->assertJsonPath('is_verified', true)
            ->assertJsonPath('verification_detail', fn (string $v) => str_starts_with($v, 'Verified by the system'));

        // A task with no rule is always self-confirmed.
        $this->postJson('/api/training/modules/getting-started/tasks/search/complete')->assertOk()
            ->assertJsonPath('is_verified', false);

        $this->getJson('/api/training/modules/getting-started')->assertOk()
            ->assertJsonPath('tasks.message.is_verified', true)
            ->assertJsonPath('progress.tasks_verified', 1)
            ->assertJsonPath('progress.tasks_completed', 2);
    }

    public function test_only_a_training_manager_sees_everyones_progress_and_feedback_and_prints_others_certificates(): void
    {
        $this->getJson('/api/training/report')->assertForbidden();
        $this->getJson('/api/training/feedback')->assertForbidden();
        $this->get("/api/training/certificates/{$this->colleague->id}/getting-started")->assertForbidden();

        TrainingProgress::create([
            'organisation_id' => $this->org->id, 'user_id' => $this->colleague->id, 'module_key' => 'getting-started',
            'item_type' => TrainingProgress::LESSON, 'item_key' => 'sign-in', 'started_at' => now(), 'completed_at' => now(),
        ]);
        TrainingFeedback::create(['organisation_id' => $this->org->id, 'user_id' => $this->colleague->id, 'module_key' => 'cashier-pos', 'rating' => 2, 'comments' => 'Discounts were confusing.']);

        $this->grantPermissions(['sale.view', 'training.manage']);

        $this->getJson('/api/training')->assertOk()->assertJsonPath('can_manage', true);

        $report = $this->getJson('/api/training/report')->assertOk();
        $staff = collect($report->json('staff'))->keyBy('id');
        $this->assertTrue($staff->has($this->colleague->id));
        $this->assertSame(1, $staff[$this->colleague->id]['modules']['getting-started']['lessons_viewed']);
        $this->assertSame(['Test role'], $staff[$this->user->id]['roles']);

        $this->getJson('/api/training/feedback')->assertOk()
            ->assertJsonPath('data.0.rating', 2)
            ->assertJsonPath('data.0.module_title', 'Cashier & POS')
            ->assertJsonPath('data.0.user.name', 'Counter Two');

        // Still refused until the colleague has actually completed the module.
        $this->get("/api/training/certificates/{$this->colleague->id}/getting-started")->assertStatus(422);
    }

    public function test_another_institutions_trainees_progress_and_feedback_are_never_visible(): void
    {
        $this->grantPermissions(['sale.view', 'training.manage']);

        $orgB = Organisation::create(['name' => 'Nairobi Chemists Ltd', 'legal_name' => 'Nairobi Chemists Ltd', 'base_currency' => 'KES', 'fiscal_year_start' => 1]);
        $userB = User::create(['name' => 'B Trainee', 'username' => 'btrainee', 'email' => 'btrainee@example.test', 'password' => 'a-long-enough-password']);
        $userB->forceFill(['organisation_id' => $orgB->id])->save();
        app(TenantContext::class)->run($orgB->id, function () use ($orgB, $userB) {
            TrainingProgress::create([
                'organisation_id' => $orgB->id, 'user_id' => $userB->id, 'module_key' => 'getting-started',
                'item_type' => TrainingProgress::LESSON, 'item_key' => 'sign-in', 'started_at' => now(), 'completed_at' => now(),
            ]);
            TrainingFeedback::create(['organisation_id' => $orgB->id, 'user_id' => $userB->id, 'module_key' => 'getting-started', 'rating' => 1, 'comments' => 'Secret of B.']);
        });

        $ids = collect($this->getJson('/api/training/report')->assertOk()->json('staff'))->pluck('id');
        $this->assertFalse($ids->contains($userB->id));

        $feedback = $this->getJson('/api/training/feedback')->assertOk();
        $this->assertStringNotContainsString('Secret of B.', (string) $feedback->getContent());

        $this->get("/api/training/certificates/{$userB->id}/getting-started")->assertNotFound();
    }

    private function sendMessage(User $sender, \DateTimeInterface $at): void
    {
        $message = UserMessage::create([
            'branch_id' => $this->branch->id, 'sender_id' => $sender->id, 'recipient_id' => $sender->is($this->user) ? $this->colleague->id : $this->user->id,
            'subject' => 'Training', 'body' => 'I have started my training.', 'priority' => 'NORMAL',
        ]);
        $message->forceFill(['created_at' => $at, 'updated_at' => $at])->save();
    }
}
