<?php

namespace App\Services\Quality;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\NumberSequence;
use App\Models\ProductBatch;
use App\Models\Recall;
use App\Models\StockBalance;
use App\Models\Store;
use App\Models\WasteDisposal;
use App\Models\WasteDisposalLine;
use App\Services\Finance\JournalPoster;
use App\Services\Inventory\BatchQualityService;
use App\Services\Inventory\InsufficientStockException;
use App\Services\Inventory\StockLedgerService;
use Illuminate\Support\Facades\DB;

class InvalidWasteStatusException extends \RuntimeException {}

/**
 * Part 11.3 — the regulated disposal path. Two witnesses sign, the value
 * written off posts to the dedicated Stock write-off account (expiry or
 * damage), never buried in COGS.
 */
class WasteDisposalService
{
    public function __construct(
        private readonly StockLedgerService $ledger,
        private readonly JournalPoster $journalPoster,
        private readonly BatchQualityService $quality,
        private readonly RecallService $recalls,
    ) {}

    /**
     * @param  array{store_id: string, reason: string, user_id: int, recall_id?: ?string, disposal_method?: ?string, disposal_contractor?: ?string, certificate_reference?: ?string, ppb_reference?: ?string, witnessed_by_1?: ?int, witnessed_by_2?: ?int, notes?: ?string, photos?: ?array<int, string>, stock_effect?: bool, lines: list<array{product_id: string, batch_id: string, qty_base: string, unit_cost?: ?string}>}  $data
     */
    public function create(array $data): WasteDisposal
    {
        return DB::transaction(function () use ($data) {
            $store = Store::findOrFail($data['store_id']);
            $organisationId = (string) Branch::whereKey($store->branch_id)->value('organisation_id');

            $disposal = WasteDisposal::create([
                'organisation_id' => $organisationId,
                'branch_id' => $store->branch_id,
                'store_id' => $store->id,
                'recall_id' => $data['recall_id'] ?? null,
                'doc_number' => NumberSequence::next($organisationId, 'WASTE', $store->branch_id, 'WST'),
                'status' => 'DRAFT',
                'stock_effect' => $data['stock_effect'] ?? true,
                'reason' => $data['reason'],
                'disposal_method' => $data['disposal_method'] ?? null,
                'disposal_contractor' => $data['disposal_contractor'] ?? null,
                'certificate_reference' => $data['certificate_reference'] ?? null,
                'ppb_reference' => $data['ppb_reference'] ?? null,
                'witnessed_by_1' => $data['witnessed_by_1'] ?? null,
                'witnessed_by_2' => $data['witnessed_by_2'] ?? null,
                'photos_json' => $data['photos'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $data['user_id'],
            ]);

            $total = '0.0000';
            foreach ($data['lines'] as $line) {
                $batch = ProductBatch::findOrFail($line['batch_id']);
                if ($batch->product_id !== $line['product_id']) {
                    throw new \InvalidArgumentException("Batch {$batch->batch_number} does not belong to the product on that line.");
                }
                if (bccomp((string) $line['qty_base'], '0', 4) <= 0) {
                    throw new \InvalidArgumentException('A disposal line quantity must be positive.');
                }
                $unitCost = (string) ($line['unit_cost'] ?? $batch->landed_unit_cost);
                $value = bcmul((string) $line['qty_base'], $unitCost, 4);
                WasteDisposalLine::create([
                    'waste_disposal_id' => $disposal->id, 'product_id' => $batch->product_id, 'batch_id' => $batch->id,
                    'qty_base' => bcadd((string) $line['qty_base'], '0', 4), 'unit_cost' => $unitCost, 'line_value' => $value,
                ]);
                $total = bcadd($total, $value, 4);
            }
            $disposal->update(['total_value' => $total]);

            AuditLog::record('WASTE_RECORDED', 'waste_disposal', $disposal->id, [
                'user_id' => $data['user_id'], 'branch_id' => $store->branch_id, 'reference' => $disposal->doc_number, 'reason' => $data['reason'],
            ]);

            return $disposal->fresh(['lines']);
        });
    }

    /**
     * @param  array{disposal_method?: ?string, disposal_contractor?: ?string, certificate_reference?: ?string, ppb_reference?: ?string, witnessed_by_1?: ?int, witnessed_by_2?: ?int}  $details
     */
    public function post(WasteDisposal $disposal, int $userId, array $details = []): WasteDisposal
    {
        if ($disposal->status !== 'DRAFT') {
            throw new InvalidWasteStatusException("Waste disposal {$disposal->doc_number} is {$disposal->status}, not DRAFT.");
        }

        $disposal->fill(array_filter($details, fn ($v) => $v !== null));
        if (! $disposal->witnessed_by_1 || ! $disposal->witnessed_by_2 || (int) $disposal->witnessed_by_1 === (int) $disposal->witnessed_by_2) {
            throw new \DomainException('Two different witnesses must sign a disposal (Part 11.3).');
        }
        if (! $disposal->disposal_method) {
            throw new \DomainException('The disposal method is required before posting.');
        }

        return DB::transaction(function () use ($disposal, $userId) {
            $store = Store::findOrFail($disposal->store_id);
            $txnType = $disposal->reason === 'EXPIRED' ? 'EXPIRY_WRITE_OFF' : 'DAMAGE_WRITE_OFF';
            $expenseRole = $disposal->reason === 'EXPIRED' ? 'STOCK_WRITEOFF_EXPIRY' : 'STOCK_WRITEOFF_DAMAGE';
            $recall = $disposal->recall_id ? Recall::find($disposal->recall_id) : null;

            foreach ($disposal->lines as $line) {
                if ($disposal->stock_effect) {
                    $balance = StockBalance::where('product_id', $line->product_id)->where('batch_id', $line->batch_id)->where('store_id', $store->id)->lockForUpdate()->first();
                    $onHand = (string) ($balance->qty_on_hand ?? '0');
                    if (bccomp($onHand, (string) $line->qty_base, 4) < 0) {
                        throw new InsufficientStockException((string) $line->qty_base, $onHand, bcsub((string) $line->qty_base, $onHand, 4));
                    }

                    $this->ledger->post([
                        'txn_type' => $txnType,
                        'product_id' => $line->product_id,
                        'batch_id' => $line->batch_id,
                        'store_id' => $store->id,
                        'qty_base' => bcmul((string) $line->qty_base, '-1', 4),
                        'unit_cost' => (string) $line->unit_cost,
                        'source_doc_type' => 'waste_disposal',
                        'source_doc_id' => $disposal->id,
                        'source_doc_line_id' => $line->id,
                        'user_id' => $userId,
                        'branch_id' => $store->branch_id,
                    ]);

                    // Nothing left anywhere: the batch's story ends at DISPOSED.
                    $remaining = (string) StockBalance::where('batch_id', $line->batch_id)->sum('qty_on_hand');
                    $batch = ProductBatch::findOrFail($line->batch_id);
                    if (bccomp($remaining, '0', 4) <= 0 && in_array($batch->status, ['EXPIRED', 'QUARANTINED', 'RECALLED', 'REJECTED'], true)) {
                        $this->quality->transition($batch, 'DISPOSED', $userId, "Waste disposal {$disposal->doc_number}");
                    }
                }

                if ($recall) {
                    $this->recalls->recordDisposal($recall, $line->batch_id, (string) $line->qty_base);
                }
            }

            // Part 12.3 — Dr Stock write-off (expiry|damage) / Cr Inventory.
            // When the goods never re-entered stock the cost was already in
            // COGS, so the write-off is a reclassification out of COGS.
            if (bccomp((string) $disposal->total_value, '0', 4) > 0) {
                $this->journalPoster->post([
                    'organisation_id' => $disposal->organisation_id,
                    'branch_id' => $disposal->branch_id,
                    'entry_date' => now(),
                    'source_doc_type' => 'waste_disposal',
                    'source_doc_id' => $disposal->id,
                    'narration' => "Waste disposal {$disposal->doc_number} ({$disposal->reason})",
                    'posted_by' => $userId,
                ], [
                    ['account_role' => $expenseRole, 'debit' => (string) $disposal->total_value, 'narration' => "Write-off {$disposal->doc_number}"],
                    ['account_role' => $disposal->stock_effect ? 'INVENTORY' : 'COGS', 'credit' => (string) $disposal->total_value, 'narration' => "Write-off {$disposal->doc_number}"],
                ]);
            }

            $disposal->save();
            $disposal->update(['status' => 'POSTED', 'posted_by' => $userId, 'posted_at' => now()]);

            AuditLog::record('WASTE_DISPOSED', 'waste_disposal', $disposal->id, [
                'user_id' => $userId, 'branch_id' => $disposal->branch_id, 'reference' => $disposal->doc_number,
                'after_json' => ['total_value' => (string) $disposal->total_value, 'witnesses' => [$disposal->witnessed_by_1, $disposal->witnessed_by_2]],
            ]);

            return $disposal->fresh(['lines']);
        });
    }
}
