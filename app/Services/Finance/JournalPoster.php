<?php

namespace App\Services\Finance;

use App\Models\ChartOfAccount;
use App\Models\FinancialPeriod;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\NumberSequence;
use Illuminate\Support\Facades\DB;

class UnbalancedJournalException extends \RuntimeException
{
    public function __construct(string $debits, string $credits)
    {
        parent::__construct("Journal does not balance: debits {$debits} != credits {$credits}.");
    }
}

class NoOpenPeriodException extends \RuntimeException {}

/**
 * Part 12.1 — "For every journal_entry: Sigma debit_amount = Sigma credit_amount,
 * exactly, to 4dp." Enforced here in the service layer (the DB-level CHECK on
 * journal_entry_lines only stops a single line being both a debit and a
 * credit; the entry-wide balance is this class's job) before a single row
 * is written.
 */
class JournalPoster
{
    /**
     * @param  array{organisation_id: string, branch_id: ?string, entry_date: \DateTimeInterface, source_doc_type: string, source_doc_id: string, narration: string, posted_by: int, reverses_journal_id?: ?string}  $header
     * @param  list<array{account_role: string, debit?: string, credit?: string, branch_id?: ?string, partner_type?: ?string, partner_id?: ?string, tax_code_id?: ?string, narration?: ?string}>  $lines
     */
    public function post(array $header, array $lines): JournalEntry
    {
        $totalDebit = '0.0000';
        $totalCredit = '0.0000';
        foreach ($lines as $line) {
            $totalDebit = bcadd($totalDebit, $line['debit'] ?? '0', 4);
            $totalCredit = bcadd($totalCredit, $line['credit'] ?? '0', 4);
        }

        if (bccomp($totalDebit, $totalCredit, 4) !== 0) {
            throw new UnbalancedJournalException($totalDebit, $totalCredit);
        }

        return DB::transaction(function () use ($header, $lines) {
            $period = FinancialPeriod::openPeriodFor($header['organisation_id'], $header['entry_date']);
            if (! $period) {
                throw new NoOpenPeriodException("No open financial period covers {$header['entry_date']->format('Y-m-d')}.");
            }

            $docNumber = NumberSequence::next(
                organisationId: $header['organisation_id'],
                scope: 'JOURNAL',
                branchId: $header['branch_id'] ?? null,
                prefix: 'JE',
            );

            $journal = JournalEntry::create([
                'organisation_id' => $header['organisation_id'],
                'branch_id' => $header['branch_id'] ?? null,
                'doc_number' => $docNumber,
                'entry_date' => $header['entry_date'],
                'period_id' => $period->id,
                'source_doc_type' => $header['source_doc_type'],
                'source_doc_id' => $header['source_doc_id'],
                'narration' => $header['narration'],
                'reverses_journal_id' => $header['reverses_journal_id'] ?? null,
                'posted_by' => $header['posted_by'],
                'posted_at' => now(),
            ]);

            foreach ($lines as $i => $line) {
                $account = ChartOfAccount::byRole($header['organisation_id'], $line['account_role']);

                JournalEntryLine::create([
                    'journal_id' => $journal->id,
                    'line_number' => $i + 1,
                    'account_id' => $account->id,
                    'debit_amount' => $line['debit'] ?? '0',
                    'credit_amount' => $line['credit'] ?? '0',
                    'branch_id' => $line['branch_id'] ?? $header['branch_id'] ?? null,
                    'partner_type' => $line['partner_type'] ?? null,
                    'partner_id' => $line['partner_id'] ?? null,
                    'tax_code_id' => $line['tax_code_id'] ?? null,
                    'narration' => $line['narration'] ?? null,
                ]);
            }

            return $journal;
        });
    }
}
