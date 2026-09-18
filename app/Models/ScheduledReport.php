<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Part 20.3 — a catalogue report mailed as CSV on a schedule.
 *
 * @property-read array<string, mixed>|null $filters_json
 * @property-read list<string> $recipients
 * @property-read string $frequency
 * @property-read string $run_at
 * @property int|null $weekday
 * @property int|null $month_day
 * @property Carbon|null $next_run_at
 * @property-read Carbon|null $last_run_at
 */
class ScheduledReport extends Model
{
    use HasUuids;

    protected $fillable = [
        'organisation_id', 'branch_id', 'report_key', 'filters_json', 'frequency', 'run_at', 'weekday', 'month_day',
        'recipients', 'is_active', 'next_run_at', 'last_run_at', 'last_status', 'last_error', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'filters_json' => 'array',
            'recipients' => 'array',
            'is_active' => 'boolean',
            'weekday' => 'integer',
            'month_day' => 'integer',
            'next_run_at' => 'datetime',
            'last_run_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * The first run time strictly after $after that matches the schedule.
     */
    public function nextRunAfter(Carbon $after): Carbon
    {
        [$hour, $minute] = array_map('intval', explode(':', $this->run_at));
        $candidate = $after->copy()->setTime($hour, $minute);

        return match ($this->frequency) {
            'DAILY' => $candidate->lte($after) ? $candidate->addDay() : $candidate,
            'WEEKLY' => $this->nextWeekly($candidate, $after),
            default => $this->nextMonthly($candidate, $after),
        };
    }

    /**
     * The reporting window a run covers: yesterday for a daily report, the
     * seven days to yesterday for a weekly one, the previous calendar month
     * for a monthly one.
     *
     * @return array{from: string, to: string}
     */
    public function windowFor(Carbon $runAt): array
    {
        $yesterday = $runAt->copy()->subDay();

        return match ($this->frequency) {
            'DAILY' => ['from' => $yesterday->toDateString(), 'to' => $yesterday->toDateString()],
            'WEEKLY' => ['from' => $yesterday->copy()->subDays(6)->toDateString(), 'to' => $yesterday->toDateString()],
            default => ['from' => $runAt->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), 'to' => $runAt->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
        };
    }

    private function nextWeekly(Carbon $candidate, Carbon $after): Carbon
    {
        $weekday = $this->weekday ?? 1;
        $candidate = $candidate->addDays(($weekday - $candidate->dayOfWeekIso + 7) % 7);

        return $candidate->lte($after) ? $candidate->addWeek() : $candidate;
    }

    private function nextMonthly(Carbon $candidate, Carbon $after): Carbon
    {
        $candidate = $candidate->setDay($this->month_day ?? 1);

        return $candidate->lte($after) ? $candidate->addMonthNoOverflow() : $candidate;
    }
}
