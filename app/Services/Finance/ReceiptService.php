<?php

namespace App\Services\Finance;

use App\Models\AccountsReceivable;
use App\Models\AuditLog;
use App\Models\CustomerCredit;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Sale;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InvalidPaymentStatusException extends \RuntimeException {}

class PaymentPeriodClosedException extends \RuntimeException {}

/**
 * Part 10.x / V6 AR flow — a receipt reduces the customer's net AR exposure
 * the moment it's recorded; how it's spread across specific invoices (FIFO
 * by default, oldest first, or an explicit list) is sub-ledger detail
 * recorded via payment_allocations and compensating accounts_receivables
 * rows. One payment, one journal entry (Dr cash/bank/mpesa, Cr AR control),
 * regardless of how many invoices it ends up covering — allocation never
 * changes the GL posting, only which invoices show as settled.
 */
class ReceiptService
{
    public function __construct(private readonly JournalPoster $journalPoster) {}

    /**
     * @param  array{
     *     organisation_id: string, branch_id: string, customer_id: string, method: string,
     *     reference?: ?string, amount: string, received_by: int,
     *     allocations?: list<array{sale_id: string, amount: string}>,
     * }  $data
     */
    public function record(array $data): Payment
    {
        return DB::transaction(function () use ($data) {
            $payment = Payment::create([
                'organisation_id' => $data['organisation_id'],
                'branch_id' => $data['branch_id'],
                'customer_id' => $data['customer_id'],
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'amount' => $data['amount'],
                'received_by' => $data['received_by'],
                'received_at' => now(),
                'status' => 'CLEARED',
            ]);

            $credit = CustomerCredit::query()->where('customer_id', $data['customer_id'])->lockForUpdate()->first()
                ?? CustomerCredit::create(['customer_id' => $data['customer_id'], 'credit_limit' => '0']);

            $allocations = $data['allocations'] ?? $this->autoAllocate($data['customer_id'], $data['amount']);

            $allocatedTotal = array_reduce($allocations, fn ($c, $a) => bcadd($c, $a['amount'], 4), '0.0000');
            if (bccomp($allocatedTotal, $data['amount'], 4) > 0) {
                throw new \InvalidArgumentException('Allocations cannot exceed the payment amount.');
            }

            $remaining = $data['amount'];
            foreach ($allocations as $alloc) {
                $sale = Sale::findOrFail($alloc['sale_id']);
                if ($sale->customer_id !== $data['customer_id']) {
                    throw new \InvalidArgumentException("Sale {$sale->doc_number} does not belong to this customer.");
                }

                $this->postAllocation($payment, $credit, $sale->id, $alloc['amount']);
                $remaining = bcsub($remaining, $alloc['amount'], 4);
            }

            if (bccomp($remaining, '0', 4) > 0) {
                $this->postUnallocated($payment, $credit, $remaining);
            }

            $this->postJournal($payment);

            AuditLog::record('PAYMENT_RECEIVED', 'payment', $payment->id, [
                'reference' => $payment->reference,
                'after_json' => ['amount' => $data['amount'], 'method' => $data['method']],
            ]);

            return $payment->fresh(['allocations']);
        });
    }

    public function void(Payment $payment, string $reason, int $userId): Payment
    {
        if ($payment->status !== 'CLEARED') {
            throw new InvalidPaymentStatusException("Payment {$payment->id} is {$payment->status}, not CLEARED.");
        }

        $originalPeriod = JournalEntry::where('source_doc_type', 'payment')->where('source_doc_id', $payment->id)->first()?->period;
        if ($originalPeriod && $originalPeriod->status !== 'OPEN') {
            throw new PaymentPeriodClosedException("The financial period for this payment is {$originalPeriod->status}, not open.");
        }

        return DB::transaction(function () use ($payment, $reason, $userId) {
            $credit = CustomerCredit::where('customer_id', $payment->customer_id)->lockForUpdate()->firstOrFail();

            $arRows = AccountsReceivable::where('payment_id', $payment->id)->where('txn_type', 'PAYMENT')->get();
            $runningBalance = (string) $credit->current_balance;
            $unallocatedReversed = '0.0000';

            foreach ($arRows as $row) {
                $reinstateAmount = bcmul((string) $row->amount, '-1', 4);
                $runningBalance = bcadd($runningBalance, $reinstateAmount, 4);

                AccountsReceivable::create([
                    'organisation_id' => $row->organisation_id,
                    'customer_id' => $row->customer_id,
                    'txn_type' => 'ADJUSTMENT',
                    'sale_id' => $row->sale_id,
                    'payment_id' => $payment->id,
                    'amount' => $reinstateAmount,
                    'balance_after' => $runningBalance,
                    'branch_id' => $row->branch_id,
                    'created_at' => now(),
                ]);

                if ($row->sale_id === null) {
                    $unallocatedReversed = bcadd($unallocatedReversed, $reinstateAmount, 4);
                }
            }

            $credit->update([
                'current_balance' => $runningBalance,
                'unallocated_receipts' => bccomp($unallocatedReversed, '0', 4) > 0
                    ? bcsub((string) $credit->unallocated_receipts, $unallocatedReversed, 4)
                    : (string) $credit->unallocated_receipts,
            ]);

            $this->reverseJournal($payment, $reason, $userId);

            $payment->update([
                'status' => 'REVERSED', 'voided_by' => $userId, 'void_reason' => $reason, 'voided_at' => now(),
            ]);

            AuditLog::record('PAYMENT_VOIDED', 'payment', $payment->id, ['reason' => $reason]);

            return $payment->fresh();
        });
    }

    public function reconcile(Payment $payment): Payment
    {
        $payment->update(['reconciled_at' => now()]);

        return $payment->fresh();
    }

    public function outstandingBalance(string $saleId): string
    {
        return (string) AccountsReceivable::where('sale_id', $saleId)->sum('amount');
    }

    /**
     * Part 20 aging — buckets a customer's outstanding sale balances by how
     * long each invoice has been open as of a given date. Presentation
     * (statements, printable reports) is Phase 11's job; this is the data.
     *
     * @return array{current: string, d1_30: string, d31_60: string, d61_90: string, d90_plus: string, total: string}
     */
    public function agingReport(string $customerId, ?Carbon $asOf = null): array
    {
        $asOf ??= now();

        $buckets = [
            'current' => '0.0000', 'd1_30' => '0.0000', 'd31_60' => '0.0000',
            'd61_90' => '0.0000', 'd90_plus' => '0.0000',
        ];

        $sales = Sale::where('customer_id', $customerId)->where('status', 'POSTED')->get();

        foreach ($sales as $sale) {
            $outstanding = $this->outstandingBalance($sale->id);
            if (bccomp($outstanding, '0', 4) <= 0) {
                continue;
            }

            $daysOld = (int) $sale->posted_at->diffInDays($asOf, false);
            $bucket = match (true) {
                $daysOld <= 0 => 'current',
                $daysOld <= 30 => 'd1_30',
                $daysOld <= 60 => 'd31_60',
                $daysOld <= 90 => 'd61_90',
                default => 'd90_plus',
            };

            $buckets[$bucket] = bcadd($buckets[$bucket], $outstanding, 4);
        }

        $total = '0.0000';
        foreach ($buckets as $value) {
            $total = bcadd($total, $value, 4);
        }
        $buckets['total'] = $total;

        return $buckets;
    }

    /**
     * @return list<array{sale_id: string, amount: string}>
     */
    private function autoAllocate(string $customerId, string $amount): array
    {
        $remaining = $amount;
        $allocations = [];

        $sales = Sale::where('customer_id', $customerId)
            ->where('status', 'POSTED')
            ->orderBy('posted_at')
            ->get();

        foreach ($sales as $sale) {
            if (bccomp($remaining, '0', 4) <= 0) {
                break;
            }

            $outstanding = $this->outstandingBalance($sale->id);
            if (bccomp($outstanding, '0', 4) <= 0) {
                continue;
            }

            $take = bccomp($outstanding, $remaining, 4) < 0 ? $outstanding : $remaining;
            $allocations[] = ['sale_id' => $sale->id, 'amount' => $take];
            $remaining = bcsub($remaining, $take, 4);
        }

        return $allocations;
    }

    private function postAllocation(Payment $payment, CustomerCredit $credit, string $saleId, string $amount): void
    {
        PaymentAllocation::create([
            'payment_id' => $payment->id,
            'allocated_to_type' => 'sale',
            'allocated_to_id' => $saleId,
            'amount' => $amount,
        ]);

        $newBalance = bcsub((string) $credit->current_balance, $amount, 4);

        AccountsReceivable::create([
            'organisation_id' => $payment->organisation_id,
            'customer_id' => $payment->customer_id,
            'txn_type' => 'PAYMENT',
            'sale_id' => $saleId,
            'payment_id' => $payment->id,
            'amount' => bcmul($amount, '-1', 4),
            'balance_after' => $newBalance,
            'branch_id' => $payment->branch_id,
            'created_at' => now(),
        ]);

        $credit->update(['current_balance' => $newBalance]);
    }

    private function postUnallocated(Payment $payment, CustomerCredit $credit, string $amount): void
    {
        $newBalance = bcsub((string) $credit->current_balance, $amount, 4);
        $newUnallocated = bcadd((string) $credit->unallocated_receipts, $amount, 4);

        AccountsReceivable::create([
            'organisation_id' => $payment->organisation_id,
            'customer_id' => $payment->customer_id,
            'txn_type' => 'PAYMENT',
            'sale_id' => null,
            'payment_id' => $payment->id,
            'amount' => bcmul($amount, '-1', 4),
            'balance_after' => $newBalance,
            'branch_id' => $payment->branch_id,
            'created_at' => now(),
        ]);

        $credit->update(['current_balance' => $newBalance, 'unallocated_receipts' => $newUnallocated]);
    }

    private function postJournal(Payment $payment): void
    {
        $this->journalPoster->post([
            'organisation_id' => $payment->organisation_id,
            'branch_id' => $payment->branch_id,
            'entry_date' => $payment->received_at,
            'source_doc_type' => 'payment',
            'source_doc_id' => $payment->id,
            'narration' => "Receipt from customer — {$payment->method} {$payment->reference}",
            'posted_by' => $payment->received_by,
        ], [
            [
                'account_role' => PaymentMethodAccounts::role($payment->method),
                'debit' => (string) $payment->amount,
                'partner_type' => 'customer',
                'partner_id' => $payment->customer_id,
            ],
            [
                'account_role' => 'AR_CONTROL',
                'credit' => (string) $payment->amount,
                'partner_type' => 'customer',
                'partner_id' => $payment->customer_id,
            ],
        ]);
    }

    private function reverseJournal(Payment $payment, string $reason, int $userId): void
    {
        $original = JournalEntry::where('source_doc_type', 'payment')->where('source_doc_id', $payment->id)->first();
        if (! $original) {
            return;
        }

        $lines = $original->lines->map(fn (JournalEntryLine $l) => [
            'account_role' => $l->account->system_role,
            'debit' => (string) $l->credit_amount,
            'credit' => (string) $l->debit_amount,
            'partner_type' => $l->partner_type,
            'partner_id' => $l->partner_id,
            'narration' => "Reversal of payment {$payment->id} — {$reason}",
        ])->all();

        $this->journalPoster->post([
            'organisation_id' => $payment->organisation_id,
            'branch_id' => $payment->branch_id,
            'entry_date' => now(),
            'source_doc_type' => 'payment_void',
            'source_doc_id' => $payment->id,
            'narration' => "Reversal of receipt {$payment->id} — {$reason}",
            'reverses_journal_id' => $original->id,
            'posted_by' => $userId,
        ], $lines);
    }
}
