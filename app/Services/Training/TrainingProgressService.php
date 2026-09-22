<?php

namespace App\Services\Training;

use App\Models\TrainingFeedback;
use App\Models\TrainingProgress;
use App\Models\TrainingQuizAttempt;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Where each person stands in each module. A module is complete when every
 * lesson has been read, every practice task marked done and the knowledge
 * check passed; it was completed on the date the last of those happened.
 */
class TrainingProgressService
{
    /**
     * @param  list<int>  $userIds
     * @return array<int, array<string, array{
     *     lessons_viewed: int, lessons_total: int, tasks_completed: int, tasks_verified: int, tasks_total: int,
     *     attempts: int, best_score: int|null, passed: bool, feedback_rating: int|null,
     *     started: bool, completed: bool, completed_at: string|null
     * }>>
     */
    public function summaries(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        /** @var Collection<int, Collection<int, TrainingProgress>> $progress */
        $progress = TrainingProgress::whereIn('user_id', $userIds)->whereNotNull('completed_at')->get()->groupBy('user_id');
        /** @var Collection<int, Collection<int, TrainingQuizAttempt>> $attempts */
        $attempts = TrainingQuizAttempt::whereIn('user_id', $userIds)->orderBy('created_at')->get()->groupBy('user_id');
        /** @var Collection<int, Collection<int, TrainingFeedback>> $feedback */
        $feedback = TrainingFeedback::whereIn('user_id', $userIds)->get()->groupBy('user_id');

        $result = [];
        foreach ($userIds as $userId) {
            foreach (TrainingCatalogue::modules() as $module) {
                $result[$userId][$module['key']] = $this->summarise(
                    $module,
                    ($progress[$userId] ?? collect())->where('module_key', $module['key']),
                    ($attempts[$userId] ?? collect())->where('module_key', $module['key']),
                    ($feedback[$userId] ?? collect())->firstWhere('module_key', $module['key']),
                );
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $module
     * @param  Collection<int, TrainingProgress>  $progress  completed items only
     * @param  Collection<int, TrainingQuizAttempt>  $attempts  oldest first
     * @return array{
     *     lessons_viewed: int, lessons_total: int, tasks_completed: int, tasks_verified: int, tasks_total: int,
     *     attempts: int, best_score: int|null, passed: bool, feedback_rating: int|null,
     *     started: bool, completed: bool, completed_at: string|null
     * }
     */
    private function summarise(array $module, Collection $progress, Collection $attempts, ?TrainingFeedback $feedback): array
    {
        $lessonKeys = array_column($module['lessons'], 'key');
        $taskKeys = array_column($module['tasks'], 'key');

        $lessons = $progress->where('item_type', TrainingProgress::LESSON)->whereIn('item_key', $lessonKeys);
        $tasks = $progress->where('item_type', TrainingProgress::TASK)->whereIn('item_key', $taskKeys);
        $firstPass = $attempts->firstWhere('passed', true);

        $completed = $lessons->count() === count($lessonKeys) && $tasks->count() === count($taskKeys) && $firstPass !== null;

        $completedAt = null;
        if ($completed) {
            /** @var Carbon $latest */
            $latest = collect([$lessons->max('completed_at'), $tasks->max('completed_at'), $firstPass->created_at])->filter()->max();
            $completedAt = $latest->toIso8601String();
        }

        return [
            'lessons_viewed' => $lessons->count(),
            'lessons_total' => count($lessonKeys),
            'tasks_completed' => $tasks->count(),
            'tasks_verified' => $tasks->where('is_verified', true)->count(),
            'tasks_total' => count($taskKeys),
            'attempts' => $attempts->count(),
            'best_score' => $attempts->isEmpty() ? null : (int) $attempts->max('score_pct'),
            'passed' => $firstPass !== null,
            'feedback_rating' => $feedback?->rating,
            'started' => $progress->isNotEmpty() || $attempts->isNotEmpty(),
            'completed' => $completed,
            'completed_at' => $completedAt,
        ];
    }
}
