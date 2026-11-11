<?php

namespace App\Services\Finance;

use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use Illuminate\Support\Collection;

/**
 * The journals a posted document currently stands on, and the means to take
 * them back exactly. Used by document corrections: a correction never edits
 * a posted journal, it reverses it (dated on the original's own date, so a
 * closed period refuses it) and posts the corrected figures afresh.
 */
class DocumentJournals
{
    public function __construct(private readonly JournalPoster $journalPoster) {}

    /**
     * Journals for the document that are neither reversals nor reversed.
     *
     * @param  list<string>  $sourceTypes
     * @return Collection<int, JournalEntry>
     */
    public function live(string $documentId, array $sourceTypes): Collection
    {
        $journals = JournalEntry::whereIn('source_doc_type', $sourceTypes)
            ->where('source_doc_id', $documentId)
            ->with('lines.account')
            ->orderBy('posted_at')
            ->get();
        $reversedIds = $journals->pluck('reverses_journal_id')->filter()->all();

        return $journals->filter(fn (JournalEntry $journal) => $journal->reverses_journal_id === null && ! in_array($journal->id, $reversedIds, true))->values();
    }

    public function reverse(JournalEntry $original, string $sourceType, string $narration, int $userId): JournalEntry
    {
        return $this->journalPoster->post([
            'organisation_id' => $original->organisation_id,
            'branch_id' => $original->branch_id,
            'entry_date' => $original->entry_date,
            'source_doc_type' => $sourceType,
            'source_doc_id' => $original->source_doc_id,
            'narration' => "Reversal of {$original->doc_number} — {$narration}",
            'reverses_journal_id' => $original->id,
            'posted_by' => $userId,
        ], $original->lines->map(fn (JournalEntryLine $line) => [
            'account_id' => $line->account_id,
            'debit' => (string) $line->credit_amount,
            'credit' => (string) $line->debit_amount,
            'branch_id' => $line->branch_id,
            'partner_type' => $line->partner_type,
            'partner_id' => $line->partner_id,
            'tax_code_id' => $line->tax_code_id,
            'narration' => $narration,
        ])->all());
    }

    /**
     * A signed amount as a journal line on the natural side: positive is a
     * debit, negative a credit. Returns null for zero.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>|null
     */
    public static function signedLine(string $role, string $amount, string $narration, array $extra = []): ?array
    {
        $amount = bcadd($amount, '0', 4);
        if (bccomp($amount, '0', 4) === 0) {
            return null;
        }

        return ['account_role' => $role, 'narration' => $narration] + $extra + (bccomp($amount, '0', 4) > 0
            ? ['debit' => $amount]
            : ['credit' => bcmul($amount, '-1', 4)]);
    }
}
