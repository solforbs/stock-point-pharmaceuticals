<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Part 20.3 — one archived run of a scheduled report: the CSV that was
 * mailed, kept in the app so it can be proof-read and marked verified or
 * flagged.
 *
 * @property-read string $report_key
 * @property-read string $status
 * @property-read string $review_status
 * @property-read string|null $csv_path
 * @property-read string|null $csv_filename
 * @property-read list<array{key: string, label: string, type: string}>|null $columns_json
 * @property-read array<string, mixed>|null $totals_json
 * @property-read list<string>|null $emailed_to
 * @property-read Carbon $generated_at
 */
class ScheduledReportRun extends Model
{
    use BelongsToOrganisation, HasUuids;

    public const STATUSES = ['SUCCESS', 'FAILED'];

    public const REVIEW_STATUSES = ['UNREVIEWED', 'VERIFIED', 'FLAGGED'];

    protected $fillable = [
        'organisation_id', 'branch_id', 'scheduled_report_id', 'report_key', 'report_title', 'period_from', 'period_to',
        'trigger', 'triggered_by', 'generated_at', 'row_count', 'columns_json', 'totals_json', 'csv_path', 'csv_filename',
        'emailed_to', 'status', 'error', 'review_status', 'reviewed_by', 'reviewed_at', 'review_notes',
    ];

    protected $hidden = ['csv_path'];

    protected function casts(): array
    {
        return [
            'period_from' => 'date:Y-m-d',
            'period_to' => 'date:Y-m-d',
            'generated_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'row_count' => 'integer',
            'columns_json' => 'array',
            'totals_json' => 'array',
            'emailed_to' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ScheduledReport, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ScheduledReport::class, 'scheduled_report_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function hasFile(): bool
    {
        return $this->csv_path !== null;
    }
}
