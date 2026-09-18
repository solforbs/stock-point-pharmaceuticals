<?php

namespace Database\Seeders;

use App\Models\AccountsReceivable;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\Organisation;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\Sale;
use App\Models\SaleLine;
use App\Models\SaleLineBatchAllocation;
use App\Models\Store;
use App\Models\TaxCode;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class KenyanPharmaFinanceSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organisation::first();
        if (! $org) {
            $this->command?->error('Organisation not found.');

            return;
        }

        $branch = Branch::where('organisation_id', $org->id)->first();
        $admin = User::where('email', 'admin@example.com')->first();
        $userId = $admin?->id ?? 1;

        $stores = Store::where('branch_id', $branch->id)->get()->keyBy('code');
        $mainStore = $stores['MAIN'];

        $vatExempt = TaxCode::where('organisation_id', $org->id)->where('code', 'VAT_EXEMPT')->first();
        $taxCodeId = $vatExempt?->id;

        $customers = Customer::where('organisation_id', $org->id)->get()->keyBy('code');
        $lodwarHospital = $customers['CUST-HOSP-01'] ?? null;
        $kakumaHospital = $customers['CUST-HOSP-02'] ?? null;
        $turkanaChemist = $customers['CUST-PHARM-01'] ?? null;
        $lokiPharmacy = $customers['CUST-PHARM-02'] ?? null;
        $stMonicaClinic = $customers['CUST-CLINIC-01'] ?? null;

        $productAcinet = Product::where('organisation_id', $org->id)->where('code', 'A0019')->first();
        $productAction = Product::where('organisation_id', $org->id)->where('code', 'A0029')->first();
        $productAlbendazole = Product::where('organisation_id', $org->id)->where('code', 'A0003')->first();

        $batchAcinet = ProductBatch::where('product_id', $productAcinet?->id)->first();
        $batchAction = ProductBatch::where('product_id', $productAction?->id)->first();
        $batchAlbendazole = ProductBatch::where('product_id', $productAlbendazole?->id)->first();

        $today = now()->startOfDay();

        DB::transaction(function () use (
            $org, $branch, $userId, $mainStore, $taxCodeId,
            $lodwarHospital, $kakumaHospital, $turkanaChemist, $lokiPharmacy, $stMonicaClinic, $productAction, $batchAction,
            $today
        ) {
            // 1. Link Today's Wholesale Invoices into Accounts Receivable (Current bucket)
            $todayInvoices = Sale::where('branch_id', $branch->id)
                ->where('sale_mode', 'WHOLESALE')
                ->where('status', 'POSTED')
                ->whereDate('posted_at', $today)
                ->get();

            foreach ($todayInvoices as $inv) {
                $arExists = AccountsReceivable::where('sale_id', $inv->id)->exists();
                if (! $arExists && $inv->customer_id) {
                    AccountsReceivable::create([
                        'id' => (string) Str::uuid(),
                        'organisation_id' => $org->id,
                        'customer_id' => $inv->customer_id,
                        'txn_type' => 'INVOICE',
                        'sale_id' => $inv->id,
                        'amount' => $inv->grand_total,
                        'balance_after' => $inv->grand_total,
                        'branch_id' => $branch->id,
                        'created_at' => $inv->posted_at,
                    ]);
                }
            }

            // 2. Historical Credit Sales Across Aging Buckets
            $historicalCreditSales = [
                // 1–30 days aging (posted 12 days ago)
                [
                    'doc_number' => 'INV-2026-CR-01',
                    'customer' => $lodwarHospital,
                    'posted_at' => $today->copy()->subDays(12)->addHours(10),
                    'total' => '450000.0000',
                    'paid_amount' => '150000.0000',
                    'paid_at' => $today->copy()->subDays(5)->addHours(14),
                    'pay_ref' => 'NCBA-TR-99021',
                    'pay_method' => 'BANK',
                ],
                // 31–60 days aging (posted 42 days ago)
                [
                    'doc_number' => 'INV-2026-CR-02',
                    'customer' => $turkanaChemist,
                    'posted_at' => $today->copy()->subDays(42)->addHours(11),
                    'total' => '180000.0000',
                    'paid_amount' => '50000.0000',
                    'paid_at' => $today->copy()->subDays(20)->addHours(16),
                    'pay_ref' => 'QKH819201',
                    'pay_method' => 'MPESA',
                ],
                // 61–90 days aging (posted 72 days ago)
                [
                    'doc_number' => 'INV-2026-CR-03',
                    'customer' => $stMonicaClinic,
                    'posted_at' => $today->copy()->subDays(72)->addHours(9),
                    'total' => '95000.0000',
                    'paid_amount' => '0.0000',
                ],
                // 90+ days aging (posted 105 days ago)
                [
                    'doc_number' => 'INV-2026-CR-04',
                    'customer' => $lokiPharmacy,
                    'posted_at' => $today->copy()->subDays(105)->addHours(15),
                    'total' => '120000.0000',
                    'paid_amount' => '0.0000',
                ],
                // 90+ days aging (posted 115 days ago)
                [
                    'doc_number' => 'INV-2026-CR-05',
                    'customer' => $kakumaHospital,
                    'posted_at' => $today->copy()->subDays(115)->addHours(10),
                    'total' => '250000.0000',
                    'paid_amount' => '70000.0000',
                    'paid_at' => $today->copy()->subDays(60)->addHours(11),
                    'pay_ref' => 'CHQ-002819',
                    'pay_method' => 'CHEQUE',
                ],
            ];

            foreach ($historicalCreditSales as $cs) {
                $customer = $cs['customer'];
                if (! $customer) {
                    continue;
                }

                $sale = Sale::updateOrCreate(
                    ['doc_number' => $cs['doc_number']],
                    [
                        'id' => (string) Str::uuid(),
                        'organisation_id' => $org->id,
                        'branch_id' => $branch->id,
                        'store_id' => $mainStore->id,
                        'sale_mode' => 'WHOLESALE',
                        'sub_type' => 'WHOLESALE',
                        'customer_id' => $customer->id,
                        'user_id' => $userId,
                        'status' => 'POSTED',
                        'subtotal' => $cs['total'],
                        'discount_total' => '0.0000',
                        'tax_total' => '0.0000',
                        'grand_total' => $cs['total'],
                        'cost_total' => bcmul($cs['total'], '0.75', 4),
                        'idempotency_key' => 'IDEMP-HIST-'.$cs['doc_number'],
                        'etims_status' => 'SUCCESS',
                        'etims_control_code' => 'KRA-2026-'.Str::upper(Str::random(8)),
                        'etims_invoice_number' => 'ETIMS-'.$cs['doc_number'],
                        'etims_submitted_at' => $cs['posted_at'],
                        'posted_at' => $cs['posted_at'],
                    ]
                );

                SaleLine::where('sale_id', $sale->id)->delete();

                // Add realistic invoice line
                $saleLine = SaleLine::create([
                    'id' => (string) Str::uuid(),
                    'sale_id' => $sale->id,
                    'line_number' => 1,
                    'product_id' => $productAction->id,
                    'uom_id' => $productAction->base_uom_id,
                    'qty' => bcdiv($cs['total'], (string) $productAction->default_price, 4),
                    'qty_base' => bcdiv($cs['total'], (string) $productAction->default_price, 4),
                    'list_price' => (string) $productAction->default_price,
                    'unit_price' => (string) $productAction->default_price,
                    'discount_amount' => '0.0000',
                    'discount_pct' => '0.0000',
                    'tax_code_id' => $taxCodeId,
                    'tax_rate' => '0.0000',
                    'tax_amount' => '0.0000',
                    'line_total' => $cs['total'],
                    'unit_cost' => (string) $batchAction->unit_cost,
                    'line_cost' => bcmul($cs['total'], '0.75', 4),
                ]);

                SaleLineBatchAllocation::create([
                    'id' => (string) Str::uuid(),
                    'sale_line_id' => $saleLine->id,
                    'batch_id' => $batchAction->id,
                    'store_id' => $mainStore->id,
                    'qty_base' => $saleLine->qty_base,
                    'unit_cost' => $saleLine->unit_cost,
                ]);

                // Post Debit to Accounts Receivable for the full invoice
                AccountsReceivable::where('sale_id', $sale->id)->delete();

                AccountsReceivable::create([
                    'id' => (string) Str::uuid(),
                    'organisation_id' => $org->id,
                    'customer_id' => $customer->id,
                    'txn_type' => 'INVOICE',
                    'sale_id' => $sale->id,
                    'amount' => $cs['total'],
                    'balance_after' => $cs['total'],
                    'branch_id' => $branch->id,
                    'created_at' => $cs['posted_at'],
                ]);

                // Post Credit for partial payment if applicable
                if (bccomp($cs['paid_amount'], '0.0000', 4) > 0) {
                    $payment = Payment::create([
                        'id' => (string) Str::uuid(),
                        'organisation_id' => $org->id,
                        'branch_id' => $branch->id,
                        'customer_id' => $customer->id,
                        'method' => $cs['pay_method'],
                        'reference' => $cs['pay_ref'],
                        'amount' => $cs['paid_amount'],
                        'received_by' => $userId,
                        'received_at' => $cs['paid_at'],
                        'status' => 'CLEARED',
                        'reconciled_at' => $cs['paid_at'],
                    ]);

                    $remBal = bcsub($cs['total'], $cs['paid_amount'], 4);

                    AccountsReceivable::create([
                        'id' => (string) Str::uuid(),
                        'organisation_id' => $org->id,
                        'customer_id' => $customer->id,
                        'txn_type' => 'PAYMENT',
                        'sale_id' => $sale->id,
                        'payment_id' => $payment->id,
                        'amount' => '-'.$cs['paid_amount'], // Negative credit
                        'balance_after' => $remBal,
                        'branch_id' => $branch->id,
                        'created_at' => $cs['paid_at'],
                    ]);
                }
            }

            // 3. Reconcile Customer Credits Current Balance
            $allCustomers = Customer::where('organisation_id', $org->id)->get();
            foreach ($allCustomers as $c) {
                $totalAr = (string) AccountsReceivable::where('customer_id', $c->id)->sum('amount');
                CustomerCredit::updateOrCreate(
                    ['customer_id' => $c->id],
                    [
                        'current_balance' => max('0.0000', $totalAr),
                        'unallocated_receipts' => '0.0000',
                        'on_hold' => false,
                    ]
                );
            }
        });
    }
}
