<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\BankAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The institution's own bank accounts — where BANK, cheque and card receipts are paid in. */
class BankAccountController extends ApiController
{
    /** GET /api/bank-accounts — anyone who records receipts needs the list to pick from. */
    public function index(Request $request): JsonResponse
    {
        $this->requireAnyPermission($request, ['payment.record', 'payment.reconcile', 'finance.ar.view', 'admin.settings']);

        return response()->json([
            'data' => BankAccount::where('organisation_id', $this->organisationId($request))
                ->when(! $request->boolean('include_inactive'), fn ($q) => $q->where('is_active', true))
                ->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');
        $organisationId = $this->organisationId($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:60', Rule::unique('bank_accounts', 'account_number')->where('organisation_id', $organisationId)->where('bank_name', $request->input('bank_name'))],
            'account_name' => ['nullable', 'string', 'max:150'],
            'branch_name' => ['nullable', 'string', 'max:100'],
        ]);

        $account = BankAccount::create($data + ['organisation_id' => $organisationId, 'is_active' => true]);
        AuditLog::record('BANK_ACCOUNT_CREATED', 'bank_account', $account->id, ['reference' => $account->name]);

        return response()->json($account, 201);
    }

    public function update(Request $request, string $account): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');

        $account = BankAccount::where('organisation_id', $this->organisationId($request))->findOrFail($account);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'account_name' => ['nullable', 'string', 'max:150'],
            'branch_name' => ['nullable', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $account->update($data);
        AuditLog::record('BANK_ACCOUNT_UPDATED', 'bank_account', $account->id, ['reference' => $account->name, 'after_json' => $data]);

        return response()->json($account->fresh());
    }
}
