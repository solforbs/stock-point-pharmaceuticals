<?php

namespace App\Services\Inventory;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\Store;
use App\Services\Finance\JournalPoster;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OpeningStockValidationException extends \RuntimeException
{
    /**
     * @param  array<int, list<string>>  $rowErrors  keyed by 1-based row number
     */
    public function __construct(public readonly array $rowErrors)
    {
        parent::__construct(count($rowErrors).' opening-stock row(s) failed validation; nothing was posted.');
    }
}

/**
 * Part 7.1 — the go-live stock take. Physical stock already on the shelf is
 * brought into the ledger as OPENING_BALANCE rows (never as a goods receipt:
 * no supplier is owed for it), with the matching Dr Inventory / Cr Opening
 * balance equity journal so the ledger and the books agree from day one.
 *
 * The whole file is all-or-nothing: every row is validated first and a
 * single bad row posts nothing, so a half-loaded opening balance can never
 * exist. Batches arrive RELEASED because the stock is already in trade;
 * anything past its expiry date arrives EXPIRED so FEFO never offers it.
 */
class OpeningStockService
{
    public function __construct(
        private readonly StockLedgerService $ledger,
        private readonly JournalPoster $journalPoster,
    ) {}

    /**
     * @param  list<array{product_code: string, batch_number: string, expiry_date: string, qty: string|int|float, unit_cost: string|int|float, manufacture_date?: ?string}>  $rows  qty and unit_cost are per product base unit
     * @return array{reference: string, lines: int, total_qty: string, total_value: string, expired_lines: int, journal_id: ?string}
     */
    public function import(Store $store, array $rows, int $userId): array
    {
        $organisationId = (string) $store->branch()->value('organisation_id');
        [$prepared, $errors] = $this->validate($organisationId, $store, $rows);
        if ($errors !== []) {
            throw new OpeningStockValidationException($errors);
        }

        $reference = 'OPEN-'.$store->code.'-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4));

        return DB::transaction(function () use ($store, $prepared, $userId, $organisationId, $reference) {
            $totalValue = '0.0000';
            $totalQty = '0.0000';
            $expired = 0;
            $sourceId = (string) Str::uuid();

            foreach ($prepared as $index => $row) {
                $isExpired = $row['expiry']->lt(now()->startOfDay());
                $expired += $isExpired ? 1 : 0;

                $batch = ProductBatch::create([
                    'organisation_id' => $organisationId,
                    'product_id' => $row['product']->id,
                    'batch_number' => $row['batch_number'],
                    'expiry_date' => $row['expiry']->toDateString(),
                    'manufacture_date' => $row['manufacture_date'],
                    'unit_cost' => $row['unit_cost'],
                    'landed_unit_cost' => $row['unit_cost'],
                    'status' => $isExpired ? 'EXPIRED' : 'RELEASED',
                    'qc_released_by' => $isExpired ? null : $userId,
                    'qc_released_at' => $isExpired ? null : now(),
                ]);

                $this->ledger->post([
                    'organisation_id' => $organisationId,
                    'txn_type' => 'OPENING_BALANCE',
                    'product_id' => $row['product']->id,
                    'batch_id' => $batch->id,
                    'store_id' => $store->id,
                    'qty_base' => $row['qty'],
                    'unit_cost' => $row['unit_cost'],
                    'source_doc_type' => 'opening_stock',
                    'source_doc_id' => $sourceId,
                    'user_id' => $userId,
                    'branch_id' => $store->branch_id,
                ]);

                if ($isExpired) {
                    // Keep the eight-state cache honest: expired stock is held, not free to sell.
                    StockBalance::where('batch_id', $batch->id)->where('store_id', $store->id)->update(['qty_quarantined' => $row['qty']]);
                }

                $totalQty = bcadd($totalQty, $row['qty'], 4);
                $totalValue = bcadd($totalValue, bcmul($row['qty'], $row['unit_cost'], 4), 4);
            }

            $journal = null;
            if (bccomp($totalValue, '0', 4) > 0) {
                $journal = $this->journalPoster->post([
                    'organisation_id' => $organisationId,
                    'branch_id' => $store->branch_id,
                    'entry_date' => now(),
                    'source_doc_type' => 'opening_stock',
                    'source_doc_id' => $sourceId,
                    'narration' => "Opening stock {$reference}",
                    'posted_by' => $userId,
                ], [
                    ['account_role' => 'INVENTORY', 'debit' => $totalValue, 'narration' => "Opening stock {$store->code}"],
                    ['account_role' => 'OPENING_BALANCE_EQUITY', 'credit' => $totalValue, 'narration' => "Opening stock {$store->code}"],
                ]);
            }

            $summary = [
                'reference' => $reference,
                'lines' => count($prepared),
                'total_qty' => $totalQty,
                'total_value' => $totalValue,
                'expired_lines' => $expired,
                'journal_id' => $journal?->id,
            ];

            AuditLog::record('OPENING_STOCK_POSTED', 'store', $store->id, [
                'user_id' => $userId,
                'branch_id' => $store->branch_id,
                'reference' => $reference,
                'after_json' => $summary + ['source_doc_id' => $sourceId],
            ]);

            return $summary;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function validateOnly(Store $store, array $rows): void
    {
        [, $errors] = $this->validate((string) $store->branch()->value('organisation_id'), $store, $rows);
        if ($errors !== []) {
            throw new OpeningStockValidationException($errors);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: list<array{product: Product, batch_number: string, expiry: Carbon, manufacture_date: ?string, qty: string, unit_cost: string}>, 1: array<int, list<string>>}
     */
    private function validate(string $organisationId, Store $store, array $rows): array
    {
        $codes = collect($rows)->pluck('product_code')->map(fn ($c) => trim((string) $c))->unique()->values()->all();
        $products = Product::where('organisation_id', $organisationId)->whereIn('code', $codes)->get()->keyBy('code');

        $prepared = [];
        $errors = [];
        $seen = [];

        foreach ($rows as $i => $raw) {
            $line = $i + 1;
            $messages = [];

            $code = trim((string) ($raw['product_code'] ?? ''));
            $product = $products->get($code);
            if ($code === '') {
                $messages[] = 'product_code is required';
            } elseif (! $product) {
                $messages[] = "unknown product code {$code}";
            } elseif (! $product->is_active) {
                $messages[] = "product {$code} is inactive";
            }

            $batchNumber = trim((string) ($raw['batch_number'] ?? ''));
            if ($batchNumber === '') {
                $messages[] = 'batch_number is required';
            }

            $expiry = null;
            try {
                $expiry = ! empty($raw['expiry_date']) ? Carbon::parse((string) $raw['expiry_date'])->startOfDay() : null;
            } catch (\Throwable) {
                $expiry = null;
            }
            if (! $expiry) {
                $messages[] = 'expiry_date must be a date (YYYY-MM-DD)';
            }

            $qty = $this->decimal($raw['qty'] ?? null);
            if ($qty === null || bccomp($qty, '0', 4) <= 0) {
                $messages[] = 'qty must be a number greater than zero';
            } elseif ($product && $product->is_discrete && bccomp($qty, bcadd($qty, '0', 0), 4) !== 0) {
                $messages[] = "qty must be a whole number for {$code}";
            }

            $unitCost = $this->decimal($raw['unit_cost'] ?? null);
            if ($unitCost === null || bccomp($unitCost, '0', 4) < 0) {
                $messages[] = 'unit_cost must be a number of zero or more';
            }

            if ($product && $batchNumber !== '') {
                $key = $product->id.'|'.Str::upper($batchNumber);
                if (isset($seen[$key])) {
                    $messages[] = "batch {$batchNumber} for {$code} appears twice in this file (row {$seen[$key]})";
                }
                $seen[$key] = $line;

                if (ProductBatch::where('organisation_id', $organisationId)->where('product_id', $product->id)->where('batch_number', $batchNumber)->exists()) {
                    $messages[] = "batch {$batchNumber} for {$code} already exists; correct it with a stock count instead";
                }
            }

            if ($messages !== []) {
                $errors[$line] = $messages;

                continue;
            }

            if (! $product || ! $expiry) {
                continue;
            }
            $prepared[] = [
                'product' => $product,
                'batch_number' => $batchNumber,
                'expiry' => $expiry,
                'manufacture_date' => ! empty($raw['manufacture_date']) ? Carbon::parse((string) $raw['manufacture_date'])->toDateString() : null,
                'qty' => (string) $qty,
                'unit_cost' => (string) $unitCost,
            ];
        }

        if ($rows === []) {
            $errors[0] = ['the file has no rows'];
        }
        if (StockLedger::where('store_id', $store->id)->where('txn_type', '!=', 'OPENING_BALANCE')->exists()) {
            $errors[0] = array_merge($errors[0] ?? [], ["store {$store->code} has already traded; load further stock through a goods receipt or a stock count"]);
        }

        return [$prepared, $errors];
    }

    private function decimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $clean = str_replace([',', ' '], '', (string) $value);

        return is_numeric($clean) ? bcadd($clean, '0', 4) : null;
    }
}
