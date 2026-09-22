<?php

namespace App\Services\Sales;

use App\Models\AccountsReceivable;
use App\Models\AuditLog;
use App\Models\CustomerCredit;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnLine;
use App\Models\NumberSequence;
use App\Models\Recall;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleLineBatchAllocation;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\Store;
use App\Services\Finance\JournalPoster;
use App\Services\Finance\PaymentMethodAccounts;
use App\Services\Inventory\StockLedgerService;
use App\Services\Quality\RecallService;
use App\Services\Quality\WasteDisposalService;
use App\Services\Tax\EtimsService;
use Illuminate\Support\Facades\DB;

class InvalidReturnStatusException extends \RuntimeException {}

/**
 * Part 11.1 — RETURN REQUEST → inspection → disposition → stock effect →
 * credit note. Every line references the original sale line and batch so
 * the reversal happens at the original cost, and nothing re-enters
 * free-to-sell without a pharmacist's RESALEABLE decision.
 */
class CustomerReturnService
{
    public function __construct(
        private readonly StockLedgerService $ledger,
        private readonly JournalPoster $journalPoster,
        private readonly RecallService $recalls,
        private readonly WasteDisposalService $waste,
    ) {}

    /**
     * @param  array{sale_id: string, store_id: string, reason: string, user_id: int, recall_id?: ?string, refund_method?: ?string, refund_reference?: ?string, lines: list<array{sale_line_id: string, batch_id: string, qty_base: string, disposition?: ?string, inspection_notes?: ?string, return_reason?: ?string, remarks?: ?string}>}  $data
     */
    public function create(array $data): CustomerReturn
    {
        $sale = Sale::findOrFail($data['sale_id']);
        if ($sale->status !== 'POSTED') {
            throw new \DomainException("Sale {$sale->doc_number} is {$sale->status}; only a posted sale can be returned against.");
        }

        return DB::transaction(function () use ($data, $sale) {
            $store = Store::findOrFail($data['store_id']);
            $return = CustomerReturn::create([
                'organisation_id' => $sale->organisation_id,
                'branch_id' => $sale->branch_id,
                'store_id' => $store->id,
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'recall_id' => $data['recall_id'] ?? null,
                'doc_number' => NumberSequence::next($sale->organisation_id, 'CUSTOMER_RETURN', $sale->branch_id, 'CRN'),
                'status' => 'DRAFT',
                'reason' => $data['reason'],
                'refund_method' => $data['refund_method'] ?? ($sale->isCreditSale() ? 'CUSTOMER_ACCOUNT' : null),
                'refund_reference' => $data['refund_reference'] ?? null,
                'created_by' => $data['user_id'],
            ]);

            foreach ($data['lines'] as $line) {
                $saleLine = SaleLine::where('sale_id', $sale->id)->findOrFail($line['sale_line_id']);
                $allocation = SaleLineBatchAllocation::where('sale_line_id', $saleLine->id)->where('batch_id', $line['batch_id'])->first();
                if (! $allocation) {
                    throw new \InvalidArgumentException("Sale {$sale->doc_number} never issued that batch on line {$saleLine->line_number}.");
                }

                $qty = bcadd((string) $line['qty_base'], '0', 4);
                if (bccomp($qty, '0', 4) <= 0) {
                    throw new \InvalidArgumentException('A return line quantity must be positive.');
                }
                $alreadyReturned = (string) CustomerReturnLine::query()
                    ->where('sale_line_id', $saleLine->id)->where('batch_id', $allocation->batch_id)
                    ->whereHas('customerReturn', fn ($q) => $q->where('status', 'POSTED'))
                    ->sum('qty_base');
                if (bccomp(bcadd($alreadyReturned, $qty, 4), (string) $allocation->qty_base, 4) > 0) {
                    throw new \InvalidArgumentException("Only {$allocation->qty_base} units of that batch were issued on line {$saleLine->line_number}; {$alreadyReturned} already returned.");
                }

                // Refund at the original net price per base unit, never today's list.
                $lineNet = bcsub((string) $saleLine->line_total, (string) $saleLine->tax_amount, 4);
                $netPerBase = $saleLine->is_bonus || bccomp((string) $saleLine->qty_base, '0', 4) <= 0 ? '0.0000' : bcdiv($lineNet, (string) $saleLine->qty_base, 6);
                $taxPerBase = $saleLine->is_bonus || bccomp((string) $saleLine->qty_base, '0', 4) <= 0 ? '0.0000' : bcdiv((string) $saleLine->tax_amount, (string) $saleLine->qty_base, 6);
                $disposition = $line['disposition'] ?? 'QUARANTINE';
                $refundable = $disposition !== 'REJECT';

                $net = $refundable ? bcadd(bcmul($qty, $netPerBase, 6), '0', 4) : '0.0000';
                $tax = $refundable ? bcadd(bcmul($qty, $taxPerBase, 6), '0', 4) : '0.0000';

                CustomerReturnLine::create([
                    'customer_return_id' => $return->id,
                    'sale_line_id' => $saleLine->id,
                    'product_id' => $saleLine->product_id,
                    'batch_id' => $allocation->batch_id,
                    'qty_base' => $qty,
                    'disposition' => $disposition,
                    'unit_price' => bcadd($netPerBase, '0', 4),
                    'line_net' => $net,
                    'tax_amount' => $tax,
                    'line_total' => bcadd($net, $tax, 4),
                    'unit_cost' => (string) $allocation->unit_cost,
                    'line_cost' => bcmul($qty, (string) $allocation->unit_cost, 4),
                    'inspection_notes' => $line['inspection_notes'] ?? null,
                    'return_reason' => $line['return_reason'] ?? null,
                    'remarks' => $line['remarks'] ?? null,
                ]);
            }

            $this->recalculate($return);

            AuditLog::record('CUSTOMER_RETURN_REQUESTED', 'customer_return', $return->id, [
                'user_id' => $data['user_id'], 'branch_id' => $sale->branch_id, 'reference' => $return->doc_number, 'reason' => $data['reason'],
            ]);

            return $return->fresh(['lines']);
        });
    }

    /** The inspection decision per line. RESALEABLE is a pharmacist's call (quality.release), enforced by the caller. */
    public function disposition(CustomerReturnLine $line, string $disposition, ?string $notes, int $userId): CustomerReturnLine
    {
        $return = $line->customerReturn;
        if ($return->status !== 'DRAFT') {
            throw new InvalidReturnStatusException("Return {$return->doc_number} is {$return->status}, not DRAFT.");
        }
        if (! in_array($disposition, ['RESALEABLE', 'QUARANTINE', 'DESTROY', 'REJECT'], true)) {
            throw new \InvalidArgumentException("Unknown disposition {$disposition}.");
        }

        $refundable = $disposition !== 'REJECT';
        $net = $refundable ? bcadd(bcmul((string) $line->qty_base, (string) $line->unit_price, 6), '0', 4) : '0.0000';
        $saleLine = $line->saleLine;
        $taxPerBase = bccomp((string) $saleLine->qty_base, '0', 4) > 0 ? bcdiv((string) $saleLine->tax_amount, (string) $saleLine->qty_base, 6) : '0';
        $tax = $refundable && ! $saleLine->is_bonus ? bcadd(bcmul((string) $line->qty_base, $taxPerBase, 6), '0', 4) : '0.0000';

        $line->update(['disposition' => $disposition, 'inspection_notes' => $notes, 'line_net' => $net, 'tax_amount' => $tax, 'line_total' => bcadd($net, $tax, 4)]);
        $return->update(['inspected_by' => $userId]);
        $this->recalculate($return);

        return $line->fresh();
    }

    /**
     * @param  int|null  $witnessId  a second signatory, required when any line is destroyed (Part 11.3)
     */
    public function post(CustomerReturn $return, int $userId, ?int $witnessId = null): CustomerReturn
    {
        if ($return->status !== 'DRAFT') {
            throw new InvalidReturnStatusException("Return {$return->doc_number} is {$return->status}, not DRAFT.");
        }
        if ($return->lines()->where('disposition', 'DESTROY')->exists() && (! $witnessId || $witnessId === $userId)) {
            throw new \DomainException('Destroying returned goods needs a second witness who is not the person posting (Part 11.3).');
        }

        return DB::transaction(function () use ($return, $userId, $witnessId) {
            $sale = $return->sale;
            $store = Store::findOrFail($return->store_id);
            $quarantineStore = Store::where('branch_id', $store->branch_id)->where('store_type', 'QUARANTINE')->first();
            $recall = $return->recall_id ? Recall::findOrFail($return->recall_id) : null;

            $costBackToStock = '0.0000';
            $costDestroyed = '0.0000';
            $destroyLines = [];
            $destroyDisposal = null;

            foreach ($return->lines as $line) {
                switch ($line->disposition) {
                    case 'RESALEABLE':
                        $this->ledgerIn($line, $store, $return, $userId);
                        $costBackToStock = bcadd($costBackToStock, (string) $line->line_cost, 4);
                        break;

                    case 'QUARANTINE':
                        $target = $quarantineStore ?? $store;
                        $entry = $this->ledgerIn($line, $target, $return, $userId);
                        // Held on the balance until a pharmacist releases it.
                        $balance = StockBalance::where('product_id', $entry->product_id)->where('batch_id', $entry->batch_id)->where('store_id', $target->id)->lockForUpdate()->first();
                        if ($balance) {
                            $balance->update(['qty_quarantined' => bcadd((string) $balance->qty_quarantined, (string) $line->qty_base, 4)]);
                        }
                        $costBackToStock = bcadd($costBackToStock, (string) $line->line_cost, 4);
                        break;

                    case 'DESTROY':
                        $costDestroyed = bcadd($costDestroyed, (string) $line->line_cost, 4);
                        $destroyLines[] = ['product_id' => $line->product_id, 'batch_id' => $line->batch_id, 'qty_base' => (string) $line->qty_base, 'unit_cost' => (string) $line->unit_cost];
                        break;

                    case 'REJECT':
                    default:
                        break;
                }

                if ($recall && $line->disposition !== 'REJECT') {
                    $this->recalls->recordRecovery($recall, $line->batch_id, $return->customer_id, (string) $line->qty_base);
                }
            }

            // Destroyed goods never re-entered stock: a waste record exists,
            // no ledger row does, and the cost moves from COGS to write-off.
            if ($destroyLines !== []) {
                $destroyDisposal = $this->waste->create([
                    'store_id' => $store->id, 'reason' => 'DAMAGED', 'user_id' => $userId, 'recall_id' => $return->recall_id,
                    'stock_effect' => false, 'notes' => "Destroyed on customer return {$return->doc_number}", 'lines' => $destroyLines,
                ]);
            }

            // Part 12.3 — credit note: Dr Sales returns (contra) + Dr VAT payable / Cr AR or refund account.
            $lines = [];
            if (bccomp((string) $return->subtotal, '0', 4) > 0) {
                $lines[] = ['account_role' => 'SALES_RETURNS', 'debit' => (string) $return->subtotal, 'narration' => "Return {$return->doc_number} — revenue reversed"];
            }
            if (bccomp((string) $return->tax_total, '0', 4) > 0) {
                $lines[] = ['account_role' => 'VAT_OUTPUT', 'debit' => (string) $return->tax_total, 'narration' => "Return {$return->doc_number} — VAT reversed"];
            }
            if (bccomp((string) $return->grand_total, '0', 4) > 0) {
                $refundRole = $return->refund_method === 'CUSTOMER_ACCOUNT' || ! $return->refund_method ? 'AR_CONTROL' : PaymentMethodAccounts::role($return->refund_method);
                $lines[] = ['account_role' => $refundRole, 'credit' => (string) $return->grand_total, 'partner_type' => $refundRole === 'AR_CONTROL' ? 'customer' : null, 'partner_id' => $refundRole === 'AR_CONTROL' ? $return->customer_id : null, 'narration' => "Return {$return->doc_number} — credit note"];
            }
            // Cost side: goods back in stock reverse COGS; destroyed goods reclassify COGS to write-off (posted by the waste record).
            if (bccomp($costBackToStock, '0', 4) > 0) {
                $lines[] = ['account_role' => 'INVENTORY', 'debit' => $costBackToStock, 'narration' => "Return {$return->doc_number} — stock back at original cost"];
                $lines[] = ['account_role' => 'COGS', 'credit' => $costBackToStock, 'narration' => "Return {$return->doc_number} — COGS reversed"];
            }
            if ($lines !== []) {
                $this->journalPoster->post([
                    'organisation_id' => $return->organisation_id,
                    'branch_id' => $return->branch_id,
                    'entry_date' => now(),
                    'source_doc_type' => 'customer_return',
                    'source_doc_id' => $return->id,
                    'narration' => "Credit note for sale {$sale->doc_number} (return {$return->doc_number})",
                    'posted_by' => $userId,
                ], $lines);
            }

            // AR: a credit note reduces what the customer owes.
            if ($return->customer_id && ($return->refund_method === 'CUSTOMER_ACCOUNT' || ! $return->refund_method) && bccomp((string) $return->grand_total, '0', 4) > 0) {
                $credit = CustomerCredit::where('customer_id', $return->customer_id)->lockForUpdate()->first()
                    ?? CustomerCredit::create(['customer_id' => $return->customer_id, 'credit_limit' => '0']);
                $newBalance = bcsub((string) $credit->current_balance, (string) $return->grand_total, 4);
                AccountsReceivable::create([
                    'organisation_id' => $return->organisation_id, 'customer_id' => $return->customer_id, 'txn_type' => 'CREDIT_NOTE',
                    'sale_id' => $sale->id, 'amount' => bcmul((string) $return->grand_total, '-1', 4), 'balance_after' => $newBalance,
                    'branch_id' => $return->branch_id, 'created_at' => now(),
                ]);
                $credit->update(['current_balance' => $newBalance]);
            }

            if ($destroyDisposal) {
                $this->waste->post($destroyDisposal, $userId, [
                    'disposal_method' => 'Returned goods destroyed', 'witnessed_by_1' => $userId, 'witnessed_by_2' => $witnessId,
                ]);
            }

            $return->update([
                'status' => 'POSTED',
                'credit_note_number' => bccomp((string) $return->grand_total, '0', 4) > 0 ? NumberSequence::next($return->organisation_id, 'CREDIT_NOTE', $return->branch_id, 'CN') : null,
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);

            AuditLog::record('CUSTOMER_RETURN_POSTED', 'customer_return', $return->id, [
                'user_id' => $userId, 'branch_id' => $return->branch_id, 'reference' => $return->doc_number,
                'after_json' => ['grand_total' => (string) $return->grand_total, 'credit_note' => $return->credit_note_number],
            ]);

            // Part 13.6 — credit notes transmit referencing the original control code.
            app(EtimsService::class)->queueCreditNote($return->fresh());

            return $return->fresh(['lines']);
        });
    }

    public function reject(CustomerReturn $return, int $userId, string $reason): CustomerReturn
    {
        if ($return->status !== 'DRAFT') {
            throw new InvalidReturnStatusException("Return {$return->doc_number} is {$return->status}, not DRAFT.");
        }
        $return->update(['status' => 'REJECTED', 'inspected_by' => $userId]);
        AuditLog::record('CUSTOMER_RETURN_REJECTED', 'customer_return', $return->id, ['user_id' => $userId, 'reference' => $return->doc_number, 'reason' => $reason]);

        return $return->fresh(['lines']);
    }

    private function ledgerIn(CustomerReturnLine $line, Store $store, CustomerReturn $return, int $userId): StockLedger
    {
        return $this->ledger->post([
            'txn_type' => 'CUSTOMER_RETURN',
            'product_id' => $line->product_id,
            'batch_id' => $line->batch_id,
            'store_id' => $store->id,
            'qty_base' => (string) $line->qty_base,
            'unit_cost' => (string) $line->unit_cost,
            'source_doc_type' => 'customer_return',
            'source_doc_id' => $return->id,
            'source_doc_line_id' => $line->id,
            'user_id' => $userId,
            'branch_id' => $store->branch_id,
        ]);
    }

    private function recalculate(CustomerReturn $return): void
    {
        $lines = $return->lines()->get();
        $subtotal = $lines->reduce(fn ($c, $l) => bcadd($c, (string) $l->line_net, 4), '0.0000');
        $tax = $lines->reduce(fn ($c, $l) => bcadd($c, (string) $l->tax_amount, 4), '0.0000');
        $cost = $lines->where('disposition', '!=', 'REJECT')->reduce(fn ($c, $l) => bcadd($c, (string) $l->line_cost, 4), '0.0000');
        $return->update(['subtotal' => $subtotal, 'tax_total' => $tax, 'grand_total' => bcadd($subtotal, $tax, 4), 'cost_total' => $cost]);
    }
}
