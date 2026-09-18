<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Organisation;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleLineBatchAllocation;
use App\Models\StockBalance;
use App\Models\StockLedger;
use App\Models\Store;
use App\Models\TaxCode;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class KenyanPharmaSalesSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organisation::first();
        if (! $org) {
            throw new RuntimeException('Organisation not found. Run OrganisationSeeder first.');
        }

        $branch = Branch::where('organisation_id', $org->id)->first();
        $admin = User::where('email', 'admin@example.com')->first();
        $userId = $admin->id ?? 1;

        $stores = Store::where('branch_id', $branch->id)->get()->keyBy('code');
        $mainStore = $stores['MAIN'];
        $retailStore = $stores['RETAIL'];

        $vatExempt = TaxCode::where('organisation_id', $org->id)->where('code', 'VAT_EXEMPT')->first();
        $taxCodeId = $vatExempt?->id;

        $customers = Customer::where('organisation_id', $org->id)->get()->keyBy('code');
        $lodwarHospital = $customers['CUST-HOSP-01'] ?? null;
        $kakumaHospital = $customers['CUST-HOSP-02'] ?? null;
        $turkanaChemist = $customers['CUST-PHARM-01'] ?? null;
        $stMonicaClinic = $customers['CUST-CLINIC-01'] ?? null;

        $now = now();
        $today = now()->startOfDay();

        // Helper to locate product, batch, and deduct from stock cleanly
        $getProductAndBatch = function (string $productCode, string $batchNumber) use ($org) {
            $product = Product::where('organisation_id', $org->id)->where('code', (string) $productCode)->first();
            $batch = ProductBatch::where('organisation_id', $org->id)
                ->where('product_id', $product?->id)
                ->where('batch_number', $batchNumber)
                ->first();

            return [$product, $batch];
        };

        DB::transaction(function () use (
            $org, $branch, $userId, $mainStore, $retailStore, $taxCodeId,
            $lodwarHospital, $kakumaHospital, $turkanaChemist, $stMonicaClinic, $today, $getProductAndBatch
        ) {
            // ==========================================
            // 1. WHOLESALE SALES TODAY
            // ==========================================
            $wholesaleSales = [
                [
                    'doc_number' => 'INV-2026-0001',
                    'customer' => $lodwarHospital,
                    'store' => $mainStore,
                    'posted_at' => $today->copy()->addHours(8)->addMinutes(15),
                    'etims_status' => 'SUCCESS',
                    'etims_code' => 'KRA-2026-0918-00101',
                    'items' => [
                        ['code' => 'A0019', 'batch' => 'BN-ACN-2026-01', 'qty' => 30],
                        ['code' => 'A0029', 'batch' => 'BN-ACT-2026-10', 'qty' => 40],
                        ['code' => 'A0003', 'batch' => 'BN-ABZ-2026-04', 'qty' => 50],
                    ],
                ],
                [
                    'doc_number' => 'INV-2026-0002',
                    'customer' => $turkanaChemist,
                    'store' => $mainStore,
                    'posted_at' => $today->copy()->addHours(9)->addMinutes(45),
                    'etims_status' => 'SUCCESS',
                    'etims_code' => 'KRA-2026-0918-00102',
                    'items' => [
                        ['code' => 'A0019', 'batch' => 'BN-ACN-2026-01', 'qty' => 20],
                        ['code' => 'A0027', 'batch' => 'BN-ACTF-2026-02', 'qty' => 15],
                    ],
                ],
                [
                    'doc_number' => 'INV-2026-0003',
                    'customer' => $kakumaHospital,
                    'store' => $mainStore,
                    'posted_at' => $today->copy()->addHours(10)->addMinutes(30),
                    'etims_status' => 'SUCCESS',
                    'etims_code' => 'KRA-2026-0918-00103',
                    'items' => [
                        ['code' => 'A0029', 'batch' => 'BN-ACT-2026-10', 'qty' => 50],
                        ['code' => 'A0003', 'batch' => 'BN-ABZ-2026-04', 'qty' => 60],
                    ],
                ],
                // Failed eTIMS submission to trigger Dashboard Attention: "eTIMS submissions failed"
                [
                    'doc_number' => 'INV-2026-0004',
                    'customer' => $stMonicaClinic,
                    'store' => $mainStore,
                    'posted_at' => $today->copy()->addHours(11)->addMinutes(20),
                    'etims_status' => 'FAILED',
                    'etims_error' => 'KRA eTIMS ESD signature device timeout (Error 0x8821)',
                    'items' => [
                        ['code' => 'A0019', 'batch' => 'BN-ACN-2026-01', 'qty' => 15],
                        ['code' => 'A0027', 'batch' => 'BN-ACTF-2026-02', 'qty' => 10],
                    ],
                ],
            ];

            foreach ($wholesaleSales as $ws) {
                $subtotal = '0.0000';
                $costTotal = '0.0000';
                $lines = [];

                foreach ($ws['items'] as $item) {
                    [$product, $batch] = $getProductAndBatch($item['code'], $item['batch']);
                    if (! $product || ! $batch) {
                        continue;
                    }

                    $qty = (string) $item['qty'];
                    $unitPrice = (string) $product->default_price;
                    $unitCost = (string) $batch->unit_cost;
                    $lineTotal = bcmul($qty, $unitPrice, 4);
                    $lineCost = bcmul($qty, $unitCost, 4);

                    $subtotal = bcadd($subtotal, $lineTotal, 4);
                    $costTotal = bcadd($costTotal, $lineCost, 4);

                    $lines[] = [
                        'product' => $product,
                        'batch' => $batch,
                        'qty' => $qty,
                        'unit_price' => $unitPrice,
                        'unit_cost' => $unitCost,
                        'line_total' => $lineTotal,
                        'line_cost' => $lineCost,
                    ];
                }

                $sale = Sale::updateOrCreate(
                    ['doc_number' => $ws['doc_number']],
                    [
                        'id' => (string) Str::uuid(),
                        'organisation_id' => $org->id,
                        'branch_id' => $branch->id,
                        'store_id' => $ws['store']->id,
                        'sale_mode' => 'WHOLESALE',
                        'sub_type' => 'WHOLESALE',
                        'customer_id' => $ws['customer']?->id,
                        'user_id' => $userId,
                        'status' => 'POSTED',
                        'subtotal' => $subtotal,
                        'discount_total' => '0.0000',
                        'tax_total' => '0.0000',
                        'grand_total' => $subtotal,
                        'cost_total' => $costTotal,
                        'idempotency_key' => 'IDEMP-WS-'.$ws['doc_number'],
                        'etims_status' => $ws['etims_status'],
                        'etims_control_code' => $ws['etims_code'] ?? null,
                        'etims_invoice_number' => isset($ws['etims_code']) ? 'ETIMS-'.$ws['doc_number'] : null,
                        'etims_submitted_at' => $ws['etims_status'] === 'SUCCESS' ? $ws['posted_at'] : null,
                        'etims_error' => $ws['etims_error'] ?? null,
                        'posted_at' => $ws['posted_at'],
                    ]
                );

                SaleLine::where('sale_id', $sale->id)->delete();

                $lineNo = 1;
                foreach ($lines as $lineData) {
                    $saleLine = SaleLine::create([
                        'id' => (string) Str::uuid(),
                        'sale_id' => $sale->id,
                        'line_number' => $lineNo++,
                        'product_id' => $lineData['product']->id,
                        'uom_id' => $lineData['product']->base_uom_id,
                        'qty' => $lineData['qty'],
                        'qty_base' => $lineData['qty'],
                        'list_price' => $lineData['unit_price'],
                        'unit_price' => $lineData['unit_price'],
                        'discount_amount' => '0.0000',
                        'discount_pct' => '0.0000',
                        'tax_code_id' => $taxCodeId,
                        'tax_rate' => '0.0000',
                        'tax_amount' => '0.0000',
                        'line_total' => $lineData['line_total'],
                        'unit_cost' => $lineData['unit_cost'],
                        'line_cost' => $lineData['line_cost'],
                    ]);

                    SaleLineBatchAllocation::create([
                        'id' => (string) Str::uuid(),
                        'sale_line_id' => $saleLine->id,
                        'batch_id' => $lineData['batch']->id,
                        'store_id' => $ws['store']->id,
                        'qty_base' => $lineData['qty'],
                        'unit_cost' => $lineData['unit_cost'],
                    ]);

                    // Deduct stock balance
                    StockBalance::where('product_id', $lineData['product']->id)
                        ->where('batch_id', $lineData['batch']->id)
                        ->where('store_id', $ws['store']->id)
                        ->decrement('qty_on_hand', (float) $lineData['qty']);

                    // Post negative stock movement
                    StockLedger::create([
                        'id' => (string) Str::uuid(),
                        'organisation_id' => $org->id,
                        'branch_id' => $branch->id,
                        'store_id' => $ws['store']->id,
                        'product_id' => $lineData['product']->id,
                        'batch_id' => $lineData['batch']->id,
                        'txn_type' => 'SALE',
                        'qty_base' => -((float) $lineData['qty']),
                        'unit_cost' => $lineData['unit_cost'],
                        'total_cost' => -((float) $lineData['line_cost']),
                        'source_doc_type' => 'SALE',
                        'source_doc_id' => $sale->id,
                        'source_doc_line_id' => $saleLine->id,
                        'user_id' => $userId,
                        'txn_datetime' => $ws['posted_at'],
                        'created_at' => $ws['posted_at'],
                    ]);
                }
            }

            // ==========================================
            // 2. RETAIL COUNTER SALES TODAY (POS Walk-in)
            // ==========================================
            $retailSales = [
                [
                    'doc_number' => 'RCP-2026-0001',
                    'method' => 'MPESA',
                    'reference' => 'QK9912091',
                    'posted_at' => $today->copy()->addHours(8)->addMinutes(30),
                    'items' => [
                        ['code' => 'A0029', 'batch' => 'BN-ACT-2026-10', 'qty' => 2],
                    ],
                ],
                [
                    'doc_number' => 'RCP-2026-0002',
                    'method' => 'CASH',
                    'reference' => 'CASH-TILL-01',
                    'posted_at' => $today->copy()->addHours(9)->addMinutes(10),
                    'items' => [
                        ['code' => 'A0027', 'batch' => 'BN-ACTF-2026-02', 'qty' => 1],
                        ['code' => 'A0003', 'batch' => 'BN-ABZ-2026-04', 'qty' => 2],
                    ],
                ],
                [
                    'doc_number' => 'RCP-2026-0003',
                    'method' => 'MPESA',
                    'reference' => 'QK9912098',
                    'posted_at' => $today->copy()->addHours(10)->addMinutes(05),
                    'items' => [
                        ['code' => 'A0019', 'batch' => 'BN-ACN-2026-01', 'qty' => 1],
                    ],
                ],
                [
                    'doc_number' => 'RCP-2026-0004',
                    'method' => 'CASH',
                    'reference' => 'CASH-TILL-01',
                    'posted_at' => $today->copy()->addHours(11)->addMinutes(40),
                    'items' => [
                        ['code' => 'A0029', 'batch' => 'BN-ACT-2026-10', 'qty' => 1],
                    ],
                ],
                [
                    'doc_number' => 'RCP-2026-0005',
                    'method' => 'MPESA',
                    'reference' => 'QK9912110',
                    'posted_at' => $today->copy()->addHours(12)->addMinutes(15),
                    'items' => [
                        ['code' => 'A0027', 'batch' => 'BN-ACTF-2026-02', 'qty' => 2],
                    ],
                ],
            ];

            foreach ($retailSales as $rs) {
                $subtotal = '0.0000';
                $costTotal = '0.0000';
                $lines = [];

                foreach ($rs['items'] as $item) {
                    [$product, $batch] = $getProductAndBatch($item['code'], $item['batch']);
                    if (! $product || ! $batch) {
                        continue;
                    }

                    $qty = (string) $item['qty'];
                    $unitPrice = (string) $product->default_price;
                    $unitCost = (string) $batch->unit_cost;
                    $lineTotal = bcmul($qty, $unitPrice, 4);
                    $lineCost = bcmul($qty, $unitCost, 4);

                    $subtotal = bcadd($subtotal, $lineTotal, 4);
                    $costTotal = bcadd($costTotal, $lineCost, 4);

                    $lines[] = [
                        'product' => $product,
                        'batch' => $batch,
                        'qty' => $qty,
                        'unit_price' => $unitPrice,
                        'unit_cost' => $unitCost,
                        'line_total' => $lineTotal,
                        'line_cost' => $lineCost,
                    ];
                }

                $sale = Sale::updateOrCreate(
                    ['doc_number' => $rs['doc_number']],
                    [
                        'id' => (string) Str::uuid(),
                        'organisation_id' => $org->id,
                        'branch_id' => $branch->id,
                        'store_id' => $retailStore->id,
                        'sale_mode' => 'RETAIL',
                        'sub_type' => 'OTC',
                        'customer_id' => null, // Walk-in
                        'user_id' => $userId,
                        'terminal_id' => 'POS-01',
                        'status' => 'POSTED',
                        'subtotal' => $subtotal,
                        'discount_total' => '0.0000',
                        'tax_total' => '0.0000',
                        'grand_total' => $subtotal,
                        'cost_total' => $costTotal,
                        'idempotency_key' => 'IDEMP-RT-'.$rs['doc_number'],
                        'etims_status' => 'SUCCESS',
                        'etims_control_code' => 'KRA-2026-0918-'.Str::upper(Str::random(6)),
                        'etims_invoice_number' => 'ETIMS-'.$rs['doc_number'],
                        'etims_submitted_at' => $rs['posted_at'],
                        'posted_at' => $rs['posted_at'],
                    ]
                );

                SaleLine::where('sale_id', $sale->id)->delete();

                $lineNo = 1;
                foreach ($lines as $lineData) {
                    $saleLine = SaleLine::create([
                        'id' => (string) Str::uuid(),
                        'sale_id' => $sale->id,
                        'line_number' => $lineNo++,
                        'product_id' => $lineData['product']->id,
                        'uom_id' => $lineData['product']->base_uom_id,
                        'qty' => $lineData['qty'],
                        'qty_base' => $lineData['qty'],
                        'list_price' => $lineData['unit_price'],
                        'unit_price' => $lineData['unit_price'],
                        'discount_amount' => '0.0000',
                        'discount_pct' => '0.0000',
                        'tax_code_id' => $taxCodeId,
                        'tax_rate' => '0.0000',
                        'tax_amount' => '0.0000',
                        'line_total' => $lineData['line_total'],
                        'unit_cost' => $lineData['unit_cost'],
                        'line_cost' => $lineData['line_cost'],
                    ]);

                    SaleLineBatchAllocation::create([
                        'id' => (string) Str::uuid(),
                        'sale_line_id' => $saleLine->id,
                        'batch_id' => $lineData['batch']->id,
                        'store_id' => $retailStore->id,
                        'qty_base' => $lineData['qty'],
                        'unit_cost' => $lineData['unit_cost'],
                    ]);

                    // Deduct retail stock
                    StockBalance::where('product_id', $lineData['product']->id)
                        ->where('batch_id', $lineData['batch']->id)
                        ->where('store_id', $retailStore->id)
                        ->decrement('qty_on_hand', (float) $lineData['qty']);

                    // Post negative stock movement
                    StockLedger::create([
                        'id' => (string) Str::uuid(),
                        'organisation_id' => $org->id,
                        'branch_id' => $branch->id,
                        'store_id' => $retailStore->id,
                        'product_id' => $lineData['product']->id,
                        'batch_id' => $lineData['batch']->id,
                        'txn_type' => 'SALE',
                        'qty_base' => -((float) $lineData['qty']),
                        'unit_cost' => $lineData['unit_cost'],
                        'total_cost' => -((float) $lineData['line_cost']),
                        'source_doc_type' => 'SALE',
                        'source_doc_id' => $sale->id,
                        'source_doc_line_id' => $saleLine->id,
                        'user_id' => $userId,
                        'txn_datetime' => $rs['posted_at'],
                        'created_at' => $rs['posted_at'],
                    ]);
                }

                // Create paid receipt record
                Payment::create([
                    'id' => (string) Str::uuid(),
                    'organisation_id' => $org->id,
                    'branch_id' => $branch->id,
                    'customer_id' => null,
                    'method' => $rs['method'],
                    'reference' => $rs['reference'],
                    'amount' => $subtotal,
                    'received_by' => $userId,
                    'received_at' => $rs['posted_at'],
                    'status' => 'CLEARED',
                    'reconciled_at' => $rs['posted_at'],
                ]);
            }

            // ==========================================
            // 3. VOIDED SALE TODAY
            // ==========================================
            Sale::updateOrCreate(
                ['doc_number' => 'RCP-2026-0006'],
                [
                    'id' => (string) Str::uuid(),
                    'organisation_id' => $org->id,
                    'branch_id' => $branch->id,
                    'store_id' => $retailStore->id,
                    'sale_mode' => 'RETAIL',
                    'sub_type' => 'OTC',
                    'customer_id' => null,
                    'user_id' => $userId,
                    'terminal_id' => 'POS-01',
                    'status' => 'VOIDED',
                    'subtotal' => '679.1000',
                    'discount_total' => '0.0000',
                    'tax_total' => '0.0000',
                    'grand_total' => '679.1000',
                    'cost_total' => '500.0000',
                    'idempotency_key' => 'IDEMP-VOID-RCP-2026-0006',
                    'voided_by' => $userId,
                    'void_reason' => 'Customer changed payment method and walked out before completion',
                    'voided_at' => $today->copy()->addHours(12)->addMinutes(45),
                    'posted_at' => $today->copy()->addHours(12)->addMinutes(40),
                ]
            );
        });
    }
}
