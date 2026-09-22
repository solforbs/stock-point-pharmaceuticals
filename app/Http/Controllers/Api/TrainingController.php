<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\TrainingFeedback;
use App\Models\TrainingProgress;
use App\Models\TrainingQuizAttempt;
use App\Models\User;
use App\Services\Documents\PdfRenderer;
use App\Services\Training\TrainingCatalogue;
use App\Services\Training\TrainingProgressService;
use App\Services\Training\TrainingTaskVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The training centre (client item 17): lessons by role, practice tasks done
 * in the real system, a knowledge check per module and feedback. Every
 * signed-in person may train; seeing everyone's progress needs
 * `training.manage`.
 *
 * The knowledge-check answers never leave the server: questions go out
 * without them, and a submitted attempt comes back as a score and the
 * questions to revisit, never as the right options.
 */
class TrainingController extends ApiController
{
    public function __construct(private TrainingProgressService $progress) {}

    /** GET /api/training — every module, with the signed-in person's standing in each. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $this->organisationId($request);
        $roles = $this->roleNames($user->id);
        $summaries = $this->progress->summaries([$user->id])[$user->id];

        return response()->json([
            'pass_mark' => TrainingCatalogue::PASS_MARK,
            'can_manage' => $user->can('training.manage'),
            'roles' => $roles,
            'modules' => array_map(fn (array $module) => [
                'key' => $module['key'],
                'title' => $module['title'],
                'summary' => $module['summary'],
                'audience' => $module['audience'],
                'recommended' => TrainingCatalogue::isRecommendedFor($module, $roles),
                'lesson_count' => count($module['lessons']),
                'task_count' => count($module['tasks']),
                'question_count' => count($module['quiz']),
                'progress' => $summaries[$module['key']],
            ], TrainingCatalogue::modules()),
        ]);
    }

    /** GET /api/training/modules/{module} — the module's content and the person's marks against it. */
    public function show(Request $request, string $module): JsonResponse
    {
        $user = $request->user();
        $this->organisationId($request);
        $definition = $this->module($module);

        $items = TrainingProgress::where('user_id', $user->id)->where('module_key', $module)->get();
        $feedback = TrainingFeedback::where('user_id', $user->id)->where('module_key', $module)->first();
        $attempts = TrainingQuizAttempt::where('user_id', $user->id)->where('module_key', $module)->latest()->get();

        return response()->json([
            'pass_mark' => TrainingCatalogue::PASS_MARK,
            'module' => TrainingCatalogue::forBrowser($definition),
            'progress' => $this->progress->summaries([$user->id])[$user->id][$module],
            'lessons_viewed' => $items->where('item_type', TrainingProgress::LESSON)->whereNotNull('completed_at')
                ->mapWithKeys(fn (TrainingProgress $p) => [$p->item_key => $p->completed_at?->toIso8601String()]),
            'tasks' => $items->where('item_type', TrainingProgress::TASK)
                ->mapWithKeys(fn (TrainingProgress $p) => [$p->item_key => $this->taskPayload($p)]),
            'attempts' => $attempts->map(fn (TrainingQuizAttempt $a) => $a->only(['id', 'correct', 'total', 'score_pct', 'passed', 'created_at']))->values(),
            'feedback' => $feedback?->only(['rating', 'comments', 'updated_at']),
        ]);
    }

    /** POST /api/training/modules/{module}/lessons/{lesson}/viewed */
    public function viewLesson(Request $request, string $module, string $lesson): JsonResponse
    {
        $user = $request->user();
        $organisationId = $this->organisationId($request);
        $this->item($this->module($module), 'lessons', $lesson);

        $progress = TrainingProgress::firstOrNew([
            'user_id' => $user->id, 'module_key' => $module, 'item_type' => TrainingProgress::LESSON, 'item_key' => $lesson,
        ]);
        if (! $progress->completed_at) {
            $progress->fill(['organisation_id' => $organisationId, 'started_at' => $progress->started_at ?? now(), 'completed_at' => now()])->save();
        }

        return response()->json(['lesson' => $lesson, 'viewed_at' => $progress->completed_at?->toIso8601String()]);
    }

    /**
     * POST /api/training/modules/{module}/tasks/{task}/start — the moment the
     * practice begins. Only records created after it count as proof.
     */
    public function startTask(Request $request, string $module, string $task): JsonResponse
    {
        $user = $request->user();
        $organisationId = $this->organisationId($request);
        $this->item($this->module($module), 'tasks', $task);

        $progress = TrainingProgress::firstOrNew([
            'user_id' => $user->id, 'module_key' => $module, 'item_type' => TrainingProgress::TASK, 'item_key' => $task,
        ]);
        // Starting again after finishing is a fresh attempt.
        $progress->fill([
            'organisation_id' => $organisationId, 'started_at' => now(), 'completed_at' => null,
            'is_verified' => false, 'verification_detail' => null,
        ])->save();

        return response()->json($this->taskPayload($progress));
    }

    /**
     * POST /api/training/modules/{module}/tasks/{task}/complete — the trainee
     * says it is done; the system checks the records where it can.
     */
    public function completeTask(Request $request, string $module, string $task, TrainingTaskVerifier $verifier): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $user = $request->user();
        $organisationId = $this->organisationId($request);
        $definition = $this->item($this->module($module), 'tasks', $task);

        $progress = TrainingProgress::firstOrNew([
            'user_id' => $user->id, 'module_key' => $module, 'item_type' => TrainingProgress::TASK, 'item_key' => $task,
        ]);
        $startedAt = $progress->started_at ?? now();
        $result = $verifier->verify($definition, $user, Carbon::parse($startedAt));

        $progress->fill([
            'organisation_id' => $organisationId,
            'started_at' => $startedAt,
            'completed_at' => now(),
            'is_verified' => $result['verified'],
            'verification_detail' => $result['detail'],
            'note' => $data['note'] ?? null,
        ])->save();

        return response()->json($this->taskPayload($progress));
    }

    /**
     * POST /api/training/modules/{module}/quiz — scores an attempt here, where
     * the answers are. Unanswered questions count as wrong.
     */
    public function submitQuiz(Request $request, string $module): JsonResponse
    {
        $data = $request->validate([
            'answers' => ['present', 'array'],
            'answers.*' => ['nullable', 'integer', 'min:0', 'max:9'],
        ]);
        $user = $request->user();
        $organisationId = $this->organisationId($request);
        $definition = $this->module($module);

        $correct = 0;
        $review = [];
        $chosen = [];
        foreach ($definition['quiz'] as $question) {
            $answer = $data['answers'][$question['id']] ?? null;
            $chosen[$question['id']] = $answer;
            if ($answer !== null && (int) $answer === $question['answer']) {
                $correct++;
            } else {
                $review[] = ['question_id' => $question['id'], 'lesson' => $question['lesson'] ?? null];
            }
        }
        $total = count($definition['quiz']);
        $score = $total > 0 ? (int) floor($correct * 100 / $total) : 0;

        $attempt = TrainingQuizAttempt::create([
            'organisation_id' => $organisationId,
            'user_id' => $user->id,
            'module_key' => $module,
            'correct' => $correct,
            'total' => $total,
            'score_pct' => $score,
            'passed' => $score >= TrainingCatalogue::PASS_MARK,
            'answers' => $chosen,
        ]);

        return response()->json([
            'attempt' => $attempt->only(['id', 'correct', 'total', 'score_pct', 'passed', 'created_at']),
            'best_score' => (int) TrainingQuizAttempt::where('user_id', $user->id)->where('module_key', $module)->max('score_pct'),
            'pass_mark' => TrainingCatalogue::PASS_MARK,
            'review' => $review,
        ], 201);
    }

    /** POST /api/training/modules/{module}/feedback — a rating and what was unclear. */
    public function feedback(Request $request, string $module): JsonResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comments' => ['nullable', 'string', 'max:3000'],
        ]);
        $user = $request->user();
        $organisationId = $this->organisationId($request);
        $this->module($module);

        $feedback = TrainingFeedback::updateOrCreate(
            ['user_id' => $user->id, 'module_key' => $module],
            ['organisation_id' => $organisationId, 'rating' => $data['rating'], 'comments' => $data['comments'] ?? null],
        );

        return response()->json($feedback->only(['rating', 'comments', 'updated_at']));
    }

    /**
     * GET /api/training/report — everyone in the institution against every
     * module: progress, best score and the date each module was completed.
     */
    public function report(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'training.manage');

        $users = User::where('organisation_id', $this->organisationId($request))
            ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')->get(['id', 'name', 'username', 'email', 'is_active']);
        $summaries = $this->progress->summaries($users->pluck('id')->all());
        $roles = $this->roleNamesFor($users->pluck('id')->all());

        return response()->json([
            'pass_mark' => TrainingCatalogue::PASS_MARK,
            'modules' => array_map(fn (array $m) => ['key' => $m['key'], 'title' => $m['title'], 'audience' => $m['audience']], TrainingCatalogue::modules()),
            'staff' => $users->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'is_active' => $u->is_active,
                'roles' => $roles[$u->id] ?? [],
                'modules' => $summaries[$u->id],
            ])->values(),
        ]);
    }

    /** GET /api/training/feedback — what trainees said, newest first. */
    public function feedbackIndex(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'training.manage');
        $this->organisationId($request);

        $titles = array_column(TrainingCatalogue::modules(), 'title', 'key');
        $rows = TrainingFeedback::with('user:id,name,username')
            ->when($request->input('module'), fn ($q, $m) => $q->where('module_key', $m))
            ->latest('updated_at')->paginate($request->integer('per_page', 50));
        $rows->getCollection()->transform(fn (TrainingFeedback $f) => [
            'id' => $f->id,
            'module_key' => $f->module_key,
            'module_title' => $titles[$f->module_key] ?? $f->module_key,
            'rating' => $f->rating,
            'comments' => $f->comments,
            'user' => $f->user?->only(['id', 'name', 'username']),
            'updated_at' => $f->updated_at?->toIso8601String(),
        ]);

        return response()->json($rows);
    }

    /**
     * GET /api/training/certificates/{user}/{module} — a printable completion
     * certificate. A person may print their own; a manager anyone's.
     */
    public function certificate(Request $request, int $user, string $module, PdfRenderer $pdf): Response
    {
        $organisationId = $this->organisationId($request);
        if ($request->user()->id !== $user) {
            $this->requirePermission($request, 'training.manage');
        }
        $trainee = User::where('organisation_id', $organisationId)->findOrFail($user);
        $definition = $this->module($module);

        $summary = $this->progress->summaries([$trainee->id])[$trainee->id][$module];
        if (! $summary['completed']) {
            throw new HttpException(422, "{$trainee->name} has not completed \"{$definition['title']}\" yet.");
        }

        $reference = 'TRN-'.strtoupper(substr(md5($organisationId.'|'.$trainee->id.'|'.$module), 0, 8));
        AuditLog::record('TRAINING_CERTIFICATE_PRINTED', 'user', (string) $trainee->id, ['reference' => $reference]);

        return $pdf->render('pdf.training-certificate', [
            'title' => 'Certificate of Completion',
            'reference' => $reference,
            'trainee' => $trainee,
            'module' => $definition,
            'summary' => $summary,
            'completedOn' => Carbon::parse($summary['completed_at'])->format('j F Y'),
        ], $reference.'-'.$module, $request->attributes->get('active_branch_id'));
    }

    /** @return array<string, mixed> */
    private function module(string $key): array
    {
        return TrainingCatalogue::module($key) ?? throw new HttpException(404, 'There is no such training module.');
    }

    /**
     * @param  array<string, mixed>  $module
     * @return array<string, mixed>
     */
    private function item(array $module, string $collection, string $key): array
    {
        foreach ($module[$collection] as $item) {
            if ($item['key'] === $key) {
                return $item;
            }
        }

        throw new HttpException(404, 'There is no such item in this module.');
    }

    /** @return array<string, mixed> */
    private function taskPayload(TrainingProgress $progress): array
    {
        return [
            'task' => $progress->item_key,
            'started_at' => $progress->started_at?->toIso8601String(),
            'completed_at' => $progress->completed_at?->toIso8601String(),
            'is_verified' => (bool) $progress->is_verified,
            'verification_detail' => $progress->verification_detail,
            'note' => $progress->note,
        ];
    }

    /** @return list<string> */
    private function roleNames(int $userId): array
    {
        return $this->roleNamesFor([$userId])[$userId] ?? [];
    }

    /**
     * The roles each person holds in any branch.
     *
     * @param  list<int>  $userIds
     * @return array<int, list<string>>
     */
    private function roleNamesFor(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return DB::table(config('permission.table_names.model_has_roles').' as mhr')
            ->join(config('permission.table_names.roles').' as r', 'r.id', '=', 'mhr.role_id')
            ->where('mhr.model_type', User::class)->whereIn('mhr.model_id', $userIds)
            ->select('mhr.model_id', 'r.name')->distinct()->get()
            ->groupBy('model_id')
            ->map(fn ($rows) => $rows->pluck('name')->sort()->values()->all())
            ->all();
    }
}
