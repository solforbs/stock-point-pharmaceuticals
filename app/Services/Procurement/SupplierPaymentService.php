<?php

namespace App\Services\Procurement;

use App\Models\AccountsPayable;
use App\Models\AuditLog;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Services\Finance\JournalPoster;
use App\Services\Finance\PaymentMethodAccounts;
use Illuminate\Support\Facades\DB;

class SupplierOverpaymentException extends \RuntimeException {}

/**
 * Part 12.3 "Supplier payment": Dr Accounts payable / Cr Bank (or cash /
 * M-PESA clearing), with a signed AP sub-ledger row. A payment may never
 * exceed the supplier's outstanding balance — that is how a business pays
 * twice for the same delivery.
 */
class SupplierPaymentService
{
    public function __construct(private readonly JournalPoster $journalPoster) {}

    /**
     * @param  array{organisation_id: string, branch_id: string, supplier_id: string, method: string, reference?: ?string, amount: string, paid_by: int}  $data
     */
    public function pay(array $data): SupplierPayment
    {
        return DB::transaction(function () use ($data) {
            $supplier = Supplier::findOrFail($data['supplier_id']);
            $outstanding = AccountsPayable::balanceFor($supplier->id);

            if (bccomp((string) $data['amount'], $outstanding, 4) > 0) {
                throw new SupplierOverpaymentException("Payment {$data['amount']} exceeds {$supplier->name}'s outstanding balance of {$outstanding}.");
            }

            $payment = SupplierPayment::create([
                'organisation_id' => $data['organisation_id'],
                'branch_id' => $data['branch_id'],
                'supplier_id' => $supplier->id,
                'method' => $data['method'],
                'reference' => $data['reference'] ?? null,
                'amount' => $data['amount'],
                'paid_by' => $data['paid_by'],
                'paid_at' => now(),
            ]);

            AccountsPayable::create([
                'organisation_id' => $data['organisation_id'],
                'supplier_id' => $supplier->id,
                'txn_type' => 'PAYMENT',
                'supplier_payment_id' => $payment->id,
                'amount' => bcmul((string) $data['amount'], '-1', 4),
                'balance_after' => bcsub($outstanding, (string) $data['amount'], 4),
                'branch_id' => $data['branch_id'],
                'created_at' => now(),
            ]);

            $this->journalPoster->post([
                'organisation_id' => $data['organisation_id'],
                'branch_id' => $data['branch_id'],
                'entry_date' => now(),
                'source_doc_type' => 'supplier_payment',
                'source_doc_id' => $payment->id,
                'narration' => "Payment to {$supplier->name} — {$data['method']} {$data['reference']}",
                'posted_by' => $data['paid_by'],
            ], [
                ['account_role' => 'AP_CONTROL', 'debit' => (string) $data['amount'], 'partner_type' => 'supplier', 'partner_id' => $supplier->id],
                ['account_role' => PaymentMethodAccounts::role($data['method']), 'credit' => (string) $data['amount']],
            ]);

            AuditLog::record('SUPPLIER_PAID', 'supplier_payment', $payment->id, [
                'reference' => $payment->reference,
                'after_json' => ['amount' => $data['amount'], 'supplier' => $supplier->code],
            ]);

            return $payment;
        });
    }
}
