<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryLine extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'journal_id', 'line_number', 'account_id', 'debit_amount', 'credit_amount',
        'branch_id', 'partner_type', 'partner_id', 'tax_code_id', 'narration',
    ];

    protected function casts(): array
    {
        return [
            'debit_amount' => 'decimal:4',
            'credit_amount' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $line) {
            if (bccomp((string) $line->debit_amount, '0', 4) > 0 && bccomp((string) $line->credit_amount, '0', 4) > 0) {
                throw new \InvalidArgumentException('A journal entry line cannot be both a debit and a credit (Part 12.3).');
            }
        });
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_id');
    }

    /**
     * @return BelongsTo<ChartOfAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }
}
