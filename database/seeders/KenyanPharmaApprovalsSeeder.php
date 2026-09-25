<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\CustomerReturn;
use App\Models\CustomerReturnLine;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Requisition;
use App\Models\RequisitionLine;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentLine;
use App\Models\StockCount;
use App\Models\StockCountLine;
use App\Models\StockTransfer;
use App\Models\StockTransferLine;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class KenyanPharmaApprovalsSeeder extends Seeder
{
    use ResolvesSeedTargets;

    public function run(): void
    {
        $org = $this->seedOrganisation();
        if (! $org) {
            throw new RuntimeException('Organisation not found. Run OrganisationSeeder first.');
        }

        $branch = $this->seedBranch($org);
        $admin = User::where('email', 'admin@example.com')->first();
        $userId = $admin->id ?? 1;

        $stores = Store::where('branch_id', $branch->id)->get()->keyBy('code');
        $mainStore = $stores['MAIN'];
        $retailStore = $stores['RETAIL'];

        $suppliers = Supplier::where('organisation_id', $org->id)->get()->keyBy('code');
        $cosmos = $suppliers['COSMOS'] ?? null;
        $laballied = $suppliers['LABALLIED'] ?? null;
        $dawa = $suppliers['DAWA'] ?? null;

        $eaUom = UnitOfMeasure::where('code', 'EA')->first();
        $vatExempt = TaxCode::where('organisation_id', $org->id)->where('code', 'VAT_EXEMPT')->first();

        $productAcinet = Product::where('organisation_id', $org->id)->where('code', 'A0019')->first();
        $productAction = Product::where('organisation_id', $org->id)->where('code', 'A0029')->first();
        $productAlbendazole = Product::where('organisation_id', $org->id)->where('code', 'A0003')->first();
        $productActifed = Product::where('organisation_id', $org->id)->where('code', 'A0027')->first();

        $batchAcinet = ProductBatch::where('product_id', $productAcinet?->id)->first();
        $batchAction = ProductBatch::where('product_id', $productAction?->id)->first();
        $batchActifed = ProductBatch::where('product_id', $productActifed?->id)->first();

        $today = now()->startOfDay();

        DB::transaction(function () use (
            $org, $branch, $userId, $mainStore, $retailStore, $cosmos, $laballied, $dawa,
            $eaUom, $vatExempt, $productAcinet, $productAction, $productAlbendazole, $productActifed,
            $batchAcinet, $batchAction, $batchActifed, $today
        ) {
            // ========================================================
            // 1. REQUISITIONS WAITING FOR APPROVAL (Queue: 2)
            // ========================================================
            $req1 = Requisition::updateOrCreate(
                ['doc_number' => 'REQ-2026-0001'],
                [
                    'branch_id' => $branch->id,
                    'status' => 'PENDING_APPROVAL',
                    'requested_by' => $userId,
                    'needed_by' => $today->copy()->addDays(5)->toDateString(),
                    'notes' => 'Urgent restock for paediatric antibiotics and antihelminthics',
                ]
            );

            RequisitionLine::where('requisition_id', $req1->id)->delete();
            RequisitionLine::create([
                'requisition_id' => $req1->id,
                'product_id' => $productAcinet->id,
                'qty_requested' => 100,
                'notes' => 'Stock depleted below minimum',
            ]);
            RequisitionLine::create([
                'requisition_id' => $req1->id,
                'product_id' => $productAlbendazole->id,
                'qty_requested' => 200,
                'notes' => 'Upcoming sub-county school deworming campaign',
            ]);

            $req2 = Requisition::updateOrCreate(
                ['doc_number' => 'REQ-2026-0002'],
                [
                    'branch_id' => $branch->id,
                    'status' => 'PENDING_APPROVAL',
                    'requested_by' => $userId,
                    'needed_by' => $today->copy()->addDays(10)->toDateString(),
                    'notes' => 'Monthly analgesics buffer stock replenishment',
                ]
            );

            RequisitionLine::where('requisition_id', $req2->id)->delete();
            RequisitionLine::create([
                'requisition_id' => $req2->id,
                'product_id' => $productAction->id,
                'qty_requested' => 300,
                'notes' => 'Fast-moving retail and clinic orders',
            ]);

            // ========================================================
            // 2. PURCHASE ORDERS WAITING FOR APPROVAL (Queue: 2)
            // ========================================================
            $po1 = PurchaseOrder::updateOrCreate(
                ['doc_number' => 'PO-2026-0001'],
                [
                    'supplier_id' => $cosmos->id,
                    'branch_id' => $branch->id,
                    'requisition_id' => $req1->id,
                    'status' => 'PENDING_APPROVAL',
                    'created_by' => $userId,
                    'expected_date' => $today->copy()->addDays(7)->toDateString(),
                ]
            );

            PurchaseOrderLine::where('purchase_order_id', $po1->id)->delete();
            PurchaseOrderLine::create([
                'purchase_order_id' => $po1->id,
                'product_id' => $productAcinet->id,
                'uom_id' => $eaUom->id,
                'qty_ordered' => 100,
                'unit_price' => '200.0000',
                'tax_code_id' => $vatExempt?->id,
            ]);

            $po2 = PurchaseOrder::updateOrCreate(
                ['doc_number' => 'PO-2026-0002'],
                [
                    'supplier_id' => $laballied->id,
                    'branch_id' => $branch->id,
                    'status' => 'PENDING_APPROVAL',
                    'created_by' => $userId,
                    'expected_date' => $today->copy()->addDays(6)->toDateString(),
                ]
            );

            PurchaseOrderLine::where('purchase_order_id', $po2->id)->delete();
            PurchaseOrderLine::create([
                'purchase_order_id' => $po2->id,
                'product_id' => $productAction->id,
                'uom_id' => $eaUom->id,
                'qty_ordered' => 300,
                'unit_price' => '250.0000',
                'tax_code_id' => $vatExempt?->id,
            ]);

            // Also seed 2 OPEN POs for Procurement Summary tracking (SENT & APPROVED)
            PurchaseOrder::updateOrCreate(
                ['doc_number' => 'PO-2026-0003'],
                [
                    'supplier_id' => $dawa->id,
                    'branch_id' => $branch->id,
                    'status' => 'SENT',
                    'created_by' => $userId,
                    'approved_by' => $userId,
                    'sent_at' => $today->copy()->subDays(2),
                    'expected_date' => $today->copy()->addDays(5)->toDateString(),
                ]
            );

            PurchaseOrder::updateOrCreate(
                ['doc_number' => 'PO-2026-0004'],
                [
                    'supplier_id' => $cosmos->id,
                    'branch_id' => $branch->id,
                    'status' => 'PARTIALLY_RECEIVED',
                    'created_by' => $userId,
                    'approved_by' => $userId,
                    'sent_at' => $today->copy()->subDays(4),
                    'expected_date' => $today->copy()->addDays(2)->toDateString(),
                ]
            );

            // ========================================================
            // 3. STOCK ADJUSTMENTS WAITING FOR APPROVAL (Queue: 2)
            // ========================================================
            $adj1 = StockAdjustment::updateOrCreate(
                ['doc_number' => 'ADJ-2026-0001'],
                [
                    'store_id' => $mainStore->id,
                    'reason_code' => 'BREAKAGE',
                    'approval_status' => 'PENDING',
                    'created_by' => $userId,
                    'total_value' => '930.0000',
                ]
            );

            StockAdjustmentLine::where('stock_adjustment_id', $adj1->id)->delete();
            StockAdjustmentLine::create([
                'stock_adjustment_id' => $adj1->id,
                'product_id' => $productActifed->id,
                'batch_id' => $batchActifed->id,
                'qty_base' => -3, // 3 broken bottles
                'unit_cost' => '310.0000',
                'line_value' => '930.0000',
            ]);

            $adj2 = StockAdjustment::updateOrCreate(
                ['doc_number' => 'ADJ-2026-0002'],
                [
                    'store_id' => $mainStore->id,
                    'reason_code' => 'SAMPLING',
                    'approval_status' => 'PENDING',
                    'created_by' => $userId,
                    'total_value' => '400.0000',
                ]
            );

            StockAdjustmentLine::where('stock_adjustment_id', $adj2->id)->delete();
            StockAdjustmentLine::create([
                'stock_adjustment_id' => $adj2->id,
                'product_id' => $productAcinet->id,
                'batch_id' => $batchAcinet->id,
                'qty_base' => -2, // 2 packs for retain sample inspection
                'unit_cost' => '200.0000',
                'line_value' => '400.0000',
            ]);

            // ========================================================
            // 4. STOCK TRANSFERS: 1 DRAFT (Queue) + 1 IN-TRANSIT (Attention)
            // ========================================================
            // Queue: 1 transfer waiting for approval (DRAFT)
            $trDraft = StockTransfer::updateOrCreate(
                ['doc_number' => 'TR-2026-0001'],
                [
                    'from_store_id' => $mainStore->id,
                    'to_store_id' => $retailStore->id,
                    'status' => 'DRAFT',
                    'requested_by' => $userId,
                ]
            );

            StockTransferLine::where('stock_transfer_id', $trDraft->id)->delete();
            StockTransferLine::create([
                'stock_transfer_id' => $trDraft->id,
                'product_id' => $productAcinet->id,
                'batch_id' => $batchAcinet->id,
                'qty_dispatched' => 20,
            ]);

            // Attention: 1 transfer DISPATCHED (In-transit between Main & Retail)
            $trTransit = StockTransfer::updateOrCreate(
                ['doc_number' => 'TR-2026-0002'],
                [
                    'from_store_id' => $mainStore->id,
                    'to_store_id' => $retailStore->id,
                    'status' => 'DISPATCHED',
                    'requested_by' => $userId,
                    'approved_by' => $userId,
                    'dispatched_at' => $today->copy()->addHours(11),
                ]
            );

            StockTransferLine::where('stock_transfer_id', $trTransit->id)->delete();
            StockTransferLine::create([
                'stock_transfer_id' => $trTransit->id,
                'product_id' => $productAction->id,
                'batch_id' => $batchAction->id,
                'qty_dispatched' => 30,
            ]);

            // ========================================================
            // 5. STOCK COUNTS WAITING FOR APPROVAL (Queue: 1)
            // ========================================================
            $sc = StockCount::updateOrCreate(
                ['doc_number' => 'SC-2026-0001'],
                [
                    'store_id' => $mainStore->id,
                    'status' => 'REVIEW',
                    'created_by' => $userId,
                ]
            );

            StockCountLine::where('stock_count_id', $sc->id)->delete();
            StockCountLine::create([
                'stock_count_id' => $sc->id,
                'product_id' => $productAction->id,
                'batch_id' => $batchAction->id,
                'system_qty' => 150,
                'counted_qty' => 148,
                'variance_qty' => -2,
                'variance_value' => '500.0000',
                'reason_code' => 'DISCREPANCY_REVIEW',
            ]);

            // ========================================================
            // 6. CUSTOMER RETURNS WAITING FOR APPROVAL (Queue: 1)
            // ========================================================
            $sampleSale = Sale::where('branch_id', $branch->id)->where('status', 'POSTED')->first();
            $sampleSaleLine = SaleLine::where('sale_id', $sampleSale?->id)->first();
            $sampleCustomer = Customer::where('id', $sampleSale?->customer_id)->first();

            if ($sampleSale && $sampleSaleLine) {
                $cr = CustomerReturn::updateOrCreate(
                    ['doc_number' => 'RET-2026-0001'],
                    [
                        'organisation_id' => $org->id,
                        'branch_id' => $branch->id,
                        'store_id' => $mainStore->id,
                        'sale_id' => $sampleSale->id,
                        'customer_id' => $sampleCustomer?->id,
                        'status' => 'DRAFT',
                        'reason' => 'Outer carton seal damaged during transit to client facility',
                        'refund_method' => 'CUSTOMER_ACCOUNT',
                        'subtotal' => $sampleSaleLine->unit_price,
                        'tax_total' => '0.0000',
                        'grand_total' => $sampleSaleLine->unit_price,
                        'cost_total' => $sampleSaleLine->unit_cost,
                        'created_by' => $userId,
                    ]
                );

                CustomerReturnLine::where('customer_return_id', $cr->id)->delete();
                CustomerReturnLine::create([
                    'customer_return_id' => $cr->id,
                    'sale_line_id' => $sampleSaleLine->id,
                    'product_id' => $sampleSaleLine->product_id,
                    'batch_id' => $batchAcinet->id,
                    'qty_base' => 1,
                    'disposition' => 'QUARANTINE',
                    'unit_price' => $sampleSaleLine->unit_price,
                    'line_net' => $sampleSaleLine->unit_price,
                    'tax_amount' => '0.0000',
                    'line_total' => $sampleSaleLine->unit_price,
                    'unit_cost' => $sampleSaleLine->unit_cost,
                    'line_cost' => $sampleSaleLine->unit_cost,
                    'inspection_notes' => 'Awaiting QA inspection before credit note issue',
                ]);
            }
        });
    }
}
