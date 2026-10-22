<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\PettyCashVoucher;
use App\Services\Finance\PettyCashService;
use App\Services\Tenancy\TenantRules;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Petty cash: the branch float, top-ups into it and the vouchers spent from it. */
class PettyCashController extends ApiController
{
    /** GET /api/finance/petty-cash — balance, float and the recent vouchers of the active branch. */
    public function index(Request $request, PettyCashService $pettyCash): JsonResponse
    {
        $this->requirePermission($request, 'petty.cash.manage');
        $organisationId = $this->organisationId($request);
        $branchId = $this->branchId($request);
        ChartOfAccountsSeeder::provisionAccounts($organisationId);

        $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'status' => ['nullable', 'in:POSTED,VOID']]);

        $vouchers = PettyCashVoucher::where('branch_id', $branchId)
            ->when($request->input('from'), fn ($q, $v) => $q->whereDate('voucher_date', '>=', $v))
            ->when($request->input('to'), fn ($q, $v) => $q->whereDate('voucher_date', '<=', $v))
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->with(['account:id,code,name', 'creator:id,name'])
            ->orderByDesc('voucher_date')->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 25));

        $float = (string) Branch::whereKey($branchId)->value('petty_cash_float');
        $balance = $pettyCash->balance($organisationId, $branchId);

        return response()->json([
            'balance' => $balance,
            'float_limit' => number_format((float) $float, 4, '.', ''),
            'to_restore' => bccomp($float, $balance, 4) > 0 ? bcsub($float, $balance, 4) : '0.0000',
            'expense_accounts' => ChartOfAccount::where('organisation_id', $organisationId)->where('is_postable', true)->where('is_active', true)
                ->where(fn ($q) => $q->where(fn ($e) => $e->where('account_type', 'EXPENSE')->where('code', '>=', '6000'))->orWhere('system_role', 'FREIGHT_ACCRUAL'))
                ->orderBy('code')->get(['id', 'code', 'name']),
        ] + $vouchers->toArray());
    }

    /** PUT /api/finance/petty-cash/float — the imprest amount the branch is meant to hold. */
    public function setFloat(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');
        $data = $request->validate(['petty_cash_float' => ['required', 'numeric', 'min:0', 'max:100000000']]);

        $branch = Branch::findOrFail($this->branchId($request));
        $before = (string) $branch->petty_cash_float;
        $branch->update(['petty_cash_float' => (string) $data['petty_cash_float']]);
        AuditLog::record('PETTY_CASH_FLOAT_SET', 'branch', $branch->id, ['reference' => $branch->code, 'before_json' => ['float' => $before], 'after_json' => ['float' => (string) $data['petty_cash_float']]]);

        return response()->json(['float_limit' => number_format((float) $data['petty_cash_float'], 4, '.', '')]);
    }

    public function topUp(Request $request, PettyCashService $pettyCash): JsonResponse
    {
        $this->requirePermission($request, 'petty.cash.manage');

        $data = $request->validate([
            'voucher_date' => ['required', 'date', 'before_or_equal:today'],
            'funding_source' => ['required', 'in:CASH,BANK'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'description' => ['required', 'string', 'max:255'],
            'receipt_ref' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json($pettyCash->topUp($this->organisationId($request), $this->branchId($request), $request->user()->id, $data), 201);
    }

    public function spend(Request $request, PettyCashService $pettyCash): JsonResponse
    {
        $this->requirePermission($request, 'petty.cash.manage');

        $data = $request->validate([
            'voucher_date' => ['required', 'date', 'before_or_equal:today'],
            'account_id' => ['required', 'uuid', TenantRules::exists('chart_of_accounts')],
            'amount' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'payee' => ['nullable', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:255'],
            'receipt_ref' => ['nullable', 'string', 'max:100'],
        ]);

        return response()->json($pettyCash->spend($this->organisationId($request), $this->branchId($request), $request->user()->id, $data), 201);
    }

    /** PUT /api/finance/petty-cash/vouchers/{id} — correct a posted voucher (reversal + new journal). */
    public function update(Request $request, string $voucher, PettyCashService $pettyCash): JsonResponse
    {
        $this->requirePermission($request, 'petty.cash.manage');

        $data = $request->validate([
            'voucher_date' => ['required', 'date', 'before_or_equal:today'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'description' => ['required', 'string', 'max:255'],
            'account_id' => ['nullable', 'uuid', TenantRules::exists('chart_of_accounts')],
            'funding_source' => ['nullable', 'in:CASH,BANK'],
            'payee' => ['nullable', 'string', 'max:150'],
            'receipt_ref' => ['nullable', 'string', 'max:100'],
        ]);

        $voucher = PettyCashVoucher::where('branch_id', $this->branchId($request))->findOrFail($voucher);

        return response()->json($pettyCash->update($voucher, $data, $request->user()->id));
    }

    /** DELETE /api/finance/petty-cash/vouchers/{id} — reversed if still posted, then removed. */
    public function destroy(Request $request, string $voucher, PettyCashService $pettyCash): JsonResponse
    {
        $this->requirePermission($request, 'petty.cash.manage');
        $this->requirePermission($request, 'record.delete');
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);

        $voucher = PettyCashVoucher::where('branch_id', $this->branchId($request))->findOrFail($voucher);
        $pettyCash->delete($voucher, $data['reason'], $request->user()->id);

        return response()->json(['deleted' => true, 'doc_number' => $voucher->doc_number]);
    }

    /** What the caller must send to delete every voucher, so it is never one stray click. */
    public const DELETE_ALL_CONFIRMATION = 'DELETE ALL';

    /**
     * DELETE /api/finance/petty-cash/vouchers — every voucher of the active
     * branch, linked or not. Each goes the same way as a single delete: a
     * posted one is reversed first and every row is kept in the audit log,
     * so the ledger stays balanced.
     */
    public function destroyAll(Request $request, PettyCashService $pettyCash): JsonResponse
    {
        $this->requirePermission($request, 'petty.cash.manage');
        $this->requirePermission($request, 'record.delete');
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:255'],
            'confirm' => ['required', Rule::in([self::DELETE_ALL_CONFIRMATION])],
        ], ['confirm.in' => 'Type '.self::DELETE_ALL_CONFIRMATION.' to confirm.']);
        set_time_limit(300);

        $deleted = 0;
        $vouchers = PettyCashVoucher::where('branch_id', $this->branchId($request))->orderByDesc('voucher_date')->orderByDesc('created_at')->get();
        foreach ($vouchers as $voucher) {
            $pettyCash->delete($voucher, $data['reason'], $request->user()->id);
            $deleted++;
        }

        return response()->json(['deleted' => $deleted]);
    }

    public function void(Request $request, string $voucher, PettyCashService $pettyCash): JsonResponse
    {
        $this->requirePermission($request, 'petty.cash.manage');
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);

        $voucher = PettyCashVoucher::where('branch_id', $this->branchId($request))->findOrFail($voucher);

        return response()->json($pettyCash->void($voucher, $data['reason'], $request->user()->id));
    }
}
