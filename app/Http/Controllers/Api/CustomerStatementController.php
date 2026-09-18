<?php

namespace App\Http\Controllers\Api;

use App\Models\Branch;
use App\Models\Customer;
use App\Services\Finance\ReceiptService;
use App\Services\Reports\FinanceReports;
use App\Services\Reports\ReportContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Part 12.4 — the customer statement for sales and credit staff. Same
 * figures as the finance.customer_statement report (which needs
 * report.financial.view), gated instead by sale.view + finance.ar.view,
 * with the ageing buckets and the letterhead a printed statement needs.
 */
class CustomerStatementController extends ApiController
{
    /** GET /api/customers/{customer}/statement?from=&to= */
    public function show(Request $request, string $customer, FinanceReports $reports, ReceiptService $receipts): JsonResponse
    {
        $this->requirePermission($request, 'sale.view');
        $this->requirePermission($request, 'finance.ar.view');

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $organisationId = $this->organisationId($request);
        $customer = Customer::where('organisation_id', $organisationId)->with('credit')->findOrFail($customer);
        $branch = Branch::with('organisation:id,name,legal_name,kra_pin')->findOrFail($this->branchId($request));

        $ctx = ReportContext::make($organisationId, $branch->id, $filters + ['customer_id' => $customer->id]);
        $statement = $reports->customerStatement($ctx);

        return response()->json([
            'from' => $ctx->from,
            'to' => $ctx->to,
            'generated_at' => now()->toIso8601String(),
            'organisation' => $branch->organisation,
            'branch' => $branch->only(['id', 'code', 'name', 'address']),
            'customer' => $customer->only(['id', 'code', 'name', 'phone', 'email', 'address', 'payment_terms_days']) + [
                'credit_limit' => (string) ($customer->credit->credit_limit ?? '0.0000'),
            ],
            'rows' => $statement['rows'],
            'opening_balance' => $statement['totals']['opening_balance'],
            'closing_balance' => $statement['totals']['closing_balance'],
            'total_debit' => $statement['totals']['debit'] ?? '0.0000',
            'total_credit' => $statement['totals']['credit'] ?? '0.0000',
            'ageing' => $receipts->agingReport($customer->id),
        ]);
    }
}
