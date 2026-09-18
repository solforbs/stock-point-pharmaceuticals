<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use HasUuids;

    public $timestamps = false; // posted_at is the only timestamp — entries are never edited

    protected $fillable = [
        'organisation_id', 'branch_id', 'doc_number', 'entry_date', 'period_id',
        'source_doc_type', 'source_doc_id', 'narration', 'reverses_journal_id', 'posted_by', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<JournalEntryLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'journal_id');
    }

    /**
     * @return BelongsTo<FinancialPeriod, $this>
     */
    public function period(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'period_id');
    }

    public function isBalanced(): bool
    {
        $debits = (string) $this->lines()->sum('debit_amount');
        $credits = (string) $this->lines()->sum('credit_amount');

        return bccomp($debits, $credits, 4) === 0;
    }
}
