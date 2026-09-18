<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Part 17 — one standing alert: an invoice falling due, a supplier bill to
 * pay, a batch running out of shelf life. Written only by the nightly scan
 * (`alerts:scan`); users may acknowledge one, never create one.
 *
 * @property-read Carbon|null $due_date
 * @property-read Carbon|null $resolved_at
 */
class Alert extends Model
{
    use HasUuids;

    public const CATEGORIES = ['RECEIVABLE', 'PAYABLE', 'EXPIRY'];

    protected $fillable = [
        'organisation_id', 'branch_id', 'alert_key', 'category', 'type', 'severity',
        'title', 'detail', 'entity_type', 'entity_id', 'due_date', 'amount', 'link',
        'permission', 'first_seen_at', 'last_seen_at', 'resolved_at',
        'acknowledged_by', 'acknowledged_at',
    ];

    protected $appends = ['days_to_due'];

    protected function casts(): array
    {
        return [
            'due_date' => 'date:Y-m-d',
            'amount' => 'decimal:4',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    /** Negative once the date has passed, so the SPA can say "4 days late". */
    public function getDaysToDueAttribute(): ?int
    {
        return $this->due_date ? (int) now()->startOfDay()->diffInDays($this->due_date->startOfDay(), false) : null;
    }

    /**
     * @param  Builder<Alert>  $query
     * @return Builder<Alert>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }
}
