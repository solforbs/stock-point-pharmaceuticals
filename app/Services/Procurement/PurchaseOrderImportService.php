<?php

namespace App\Services\Procurement;

use App\Models\AuditLog;
use App\Models\NumberSequence;
use App\Models\Product;
use App\Models\ProductUom;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Purchase orders in bulk: the buyer keeps the order book in Excel, saves
 * it as CSV and imports it. Rows are grouped by supplier_code — one file
 * can raise several purchase orders at once — and every PO comes in as
 * DRAFT, so the normal approve → send flow still stands between the import
 * and the supplier. All-or-nothing like the other imports: one bad row
 * creates nothing.
 */
class PurchaseOrderImportService
{
    /** Columns the import reads; anything else in the file is ignored. */
    public const COLUMNS = ['supplier_code', 'product_code', 'qty', 'unit_price', 'trade_price', 'discount_pct', 'uom_code', 'expected_date'];

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{rows: int, dry_run: bool, purchase_orders: list<array{id?: string, doc_number?: string, supplier_code: string, supplier_name: string, expected_date: string|null, lines: int, total: string}>}
     *
     * @throws PurchaseOrderImportValidationException
     */
    public function import(string $organisationId, string $branchId, int $userId, array $rows, bool $dryRun, ?string $fallbackSupplierId = null): array
    {
        [$orders, $errors] = $this->validate($organisationId, $rows, $fallbackSupplierId);
        if ($errors !== []) {
            throw new PurchaseOrderImportValidationException($errors);
        }

        if ($dryRun) {
            return [
                'rows' => count($rows),
                'dry_run' => true,
                'purchase_orders' => array_map(fn (array $order) => $this->summarise($order), $orders),
            ];
        }

        $created = DB::transaction(function () use ($organisationId, $branchId, $userId, $orders) {
            $created = [];
            foreach ($orders as $order) {
                $po = PurchaseOrder::create([
                    'doc_number' => NumberSequence::next($organisationId, 'PO', $branchId, 'PO'),
                    'supplier_id' => $order['supplier']->id,
                    'branch_id' => $branchId,
                    'status' => 'DRAFT',
                    'created_by' => $userId,
                    'expected_date' => $order['expected_date'],
                ]);
                foreach ($order['lines'] as $line) {
                    PurchaseOrderLine::create(['purchase_order_id' => $po->id] + $line);
                }
                AuditLog::record('PO_CREATED', 'purchase_order', $po->id, [
                    'reference' => $po->doc_number,
                    'reason' => 'Imported from a spreadsheet',
                ]);
                $created[] = ['id' => $po->id, 'doc_number' => $po->doc_number] + $this->summarise($order);
            }

            return $created;
        });

        return ['rows' => count($rows), 'dry_run' => false, 'purchase_orders' => $created];
    }

    /**
     * @param  array{supplier: Supplier, expected_date: string|null, lines: list<array<string, mixed>>}  $order
     * @return array{supplier_code: string, supplier_name: string, expected_date: string|null, lines: int, total: string}
     */
    private function summarise(array $order): array
    {
        $total = '0';
        foreach ($order['lines'] as $line) {
            $total = bcadd($total, bcmul((string) $line['qty_ordered'], (string) $line['unit_price'], 4), 4);
        }

        return [
            'supplier_code' => (string) $order['supplier']->code,
            'supplier_name' => (string) $order['supplier']->name,
            'expected_date' => $order['expected_date'],
            'lines' => count($order['lines']),
            'total' => $total,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: list<array{supplier: Supplier, expected_date: string|null, lines: list<array<string, mixed>>}>, 1: array<int, list<string>>}
     */
    private function validate(string $organisationId, array $rows, ?string $fallbackSupplierId): array
    {
        $suppliers = $this->lookup($rows, 'supplier_code', Supplier::where('organisation_id', $organisationId));
        $fallback = $fallbackSupplierId ? Supplier::where('organisation_id', $organisationId)->find($fallbackSupplierId) : null;
        $products = $this->lookup($rows, 'product_code', Product::where('organisation_id', $organisationId));
        $uoms = UnitOfMeasure::all()->keyBy(fn (UnitOfMeasure $u) => Str::upper($u->code));
        $productUoms = ProductUom::whereIn('product_id', $products->pluck('id'))->get()->groupBy('product_id');

        /** @var array<string, array{supplier: Supplier, expected_date: string|null, lines: list<array<string, mixed>>}> $orders */
        $orders = [];
        $errors = [];
        /** @var array<string, int> $seen supplier|product|uom => first row, so a product is not ordered twice from one supplier */
        $seen = [];

        foreach ($rows as $i => $raw) {
            $line = $i + 1;
            $messages = [];
            $cell = fn (string $key): string => trim((string) ($raw[$key] ?? ''));

            $supplier = null;
            if (($supplierCode = $cell('supplier_code')) === '') {
                $supplier = $fallback;
                if (! $supplier) {
                    $messages[] = 'supplier_code is required (or choose a supplier for the whole file)';
                }
            } elseif (! ($supplier = $suppliers->get(Str::upper($supplierCode)))) {
                $messages[] = "unknown supplier code {$supplierCode}";
            }
            if ($supplier && ($supplier->status !== 'ACTIVE' || ! $supplier->is_active)) {
                $messages[] = "supplier {$supplier->name} is {$supplier->status}; no purchase order may be raised";
                $supplier = null;
            } elseif ($supplier && $supplier->licence_expiry && $supplier->licence_expiry->isPast()) {
                $messages[] = "supplier {$supplier->name}'s licence expired on {$supplier->licence_expiry->toDateString()}";
                $supplier = null;
            }
            $supplierKey = $supplier ? Str::upper((string) $supplier->code) : null;

            $product = null;
            if (($productCode = $cell('product_code')) === '') {
                $messages[] = 'product_code is required';
            } elseif (! ($product = $products->get(Str::upper($productCode)))) {
                $messages[] = "unknown product code {$productCode}";
            } elseif (! $product->is_active) {
                $messages[] = "product {$productCode} is inactive";
            }

            $uomId = null;
            if ($product) {
                /** @var Collection<int, ProductUom> $configured */
                $configured = $productUoms->get($product->id, collect());
                if (($uomCode = $cell('uom_code')) !== '') {
                    $uom = $uoms->get(Str::upper($uomCode));
                    if (! $uom) {
                        $messages[] = "unknown unit of measure {$uomCode}";
                    } elseif (! $configured->contains('uom_id', $uom->id)) {
                        $messages[] = "product {$productCode} is not set up in {$uomCode}";
                    } else {
                        $uomId = $uom->id;
                    }
                } else {
                    // No unit given: the smallest purchase pack, else the base unit.
                    $default = $configured->where('is_purchase', true)->sortBy(fn (ProductUom $u) => (float) $u->factor_to_base)->first()
                        ?? $configured->firstWhere('is_base', true);
                    if (! $default) {
                        $messages[] = "product {$productCode} has no purchase or base unit; give a uom_code";
                    } else {
                        $uomId = $default->uom_id;
                    }
                }
            }

            $qty = $this->decimal($cell('qty'));
            if ($qty === null || bccomp($qty, '0', 4) <= 0) {
                $messages[] = 'qty must be a number above zero';
            }

            $unitPrice = $cell('unit_price') === '' ? null : $this->decimal($cell('unit_price'));
            $tradePrice = $cell('trade_price') === '' ? null : $this->decimal($cell('trade_price'));
            $discount = $cell('discount_pct') === '' ? null : $this->decimal($cell('discount_pct'));
            if ($cell('unit_price') !== '' && ($unitPrice === null || bccomp($unitPrice, '0', 4) < 0)) {
                $messages[] = 'unit_price must be a number of zero or more';
            }
            if ($cell('trade_price') !== '' && ($tradePrice === null || bccomp($tradePrice, '0', 4) < 0)) {
                $messages[] = 'trade_price must be a number of zero or more';
            }
            if ($cell('discount_pct') !== '' && ($discount === null || bccomp($discount, '0', 4) < 0 || bccomp($discount, '100', 4) > 0)) {
                $messages[] = 'discount_pct must be between 0 and 100';
            }
            if ($unitPrice === null && $tradePrice === null) {
                $messages[] = 'each line needs a unit_price, or a trade_price the discount applies to';
            }

            $expected = null;
            if (($expectedDate = $cell('expected_date')) !== '') {
                try {
                    $expected = Carbon::parse($expectedDate)->toDateString();
                } catch (\Throwable) {
                    $messages[] = "expected_date {$expectedDate} is not a date (use YYYY-MM-DD)";
                }
            }

            $key = $supplier && $product ? $supplierKey.'|'.Str::upper($productCode).'|'.$uomId : null;
            if ($key !== null && isset($seen[$key])) {
                $messages[] = "product {$productCode} appears twice for supplier {$supplier->code} (row {$seen[$key]})";
            } elseif ($key !== null) {
                $seen[$key] = $line;
            }

            if ($messages !== []) {
                $errors[$line] = $messages;

                continue;
            }

            $orderKey = $supplierKey;
            $orders[$orderKey] ??= ['supplier' => $supplier, 'expected_date' => null, 'lines' => []];
            $orders[$orderKey]['expected_date'] ??= $expected;
            $orders[$orderKey]['lines'][] = collect(TradeTerms::applyTo([
                'product_id' => $product->id,
                'uom_id' => $uomId,
                'qty_ordered' => $qty,
                'unit_price' => $unitPrice,
                'trade_price' => $tradePrice,
                'discount_pct' => $discount,
            ], 'unit_price'))->only(['product_id', 'uom_id', 'qty_ordered', 'unit_price', 'trade_price', 'discount_pct'])->all();
        }

        if ($rows === []) {
            $errors[0] = ['the file has no rows'];
        }

        return [array_values($orders), $errors];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  Builder<covariant Supplier|Product>  $query
     * @return Collection<string, covariant Supplier|Product>
     */
    private function lookup(array $rows, string $column, Builder $query): Collection
    {
        $codes = collect($rows)->map(fn ($row) => trim((string) ($row[$column] ?? '')))->filter()->unique()->values()->all();

        return $query->whereIn('code', $codes)->get()->keyBy(fn ($model) => Str::upper((string) $model->code));
    }

    private function decimal(string $value): ?string
    {
        $clean = str_replace([',', ' '], '', $value);

        return is_numeric($clean) ? bcadd($clean, '0', 4) : null;
    }
}
