<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\ChartOfAccount;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The expense categories petty cash vouchers are charged to: expense
 * accounts coded 6000–6999 in the chart. An institution adds its own next
 * to the standard ones. A category that has been used, a standard one or
 * one the posting engine relies on is never deleted, only switched off, so
 * past vouchers and the ledger keep pointing at it.
 */
class ExpenseCategoryController extends ApiController
{
    /** GET /api/finance/expense-categories — every category, switched off ones too, with how often each was used. */
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'petty.cash.manage');
        $organisationId = $this->organisationId($request);
        ChartOfAccountsSeeder::provisionAccounts($organisationId);

        $categories = $this->categories($organisationId)->orderBy('code')->get(['id', 'code', 'name', 'system_role', 'is_active']);

        return response()->json(['data' => $categories->map(fn (ChartOfAccount $category) => $this->present($category))->values()]);
    }

    /** POST /api/finance/expense-categories — a new category; the code is the next free one unless given. */
    public function store(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'petty.cash.manage');
        $organisationId = $this->organisationId($request);
        ChartOfAccountsSeeder::provisionAccounts($organisationId);

        $data = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:100'],
            'code' => ['nullable', 'regex:/^6\d{3}$/', Rule::unique('chart_of_accounts', 'code')->where('organisation_id', $organisationId)],
        ], ['code.regex' => 'An expense category code is four digits from 6000 to 6999.', 'code.unique' => 'That code is already in the chart of accounts.']);

        $name = trim($data['name']);
        $this->ensureNameIsFree($organisationId, $name);
        $code = $data['code'] ?? $this->nextFreeCode($organisationId);
        if ($code === null) {
            return $this->error('NO_FREE_CODE', 'Every expense code from 6000 to 6999 is taken.', 409);
        }

        $category = ChartOfAccount::create([
            'organisation_id' => $organisationId, 'code' => $code, 'name' => $name,
            'account_type' => 'EXPENSE', 'is_postable' => true, 'is_active' => true,
        ]);
        AuditLog::record('EXPENSE_CATEGORY_CREATED', 'chart_of_account', $category->id, ['reference' => $code, 'after_json' => ['code' => $code, 'name' => $name]]);

        return response()->json($this->present($category->refresh()), 201);
    }

    /** PUT /api/finance/expense-categories/{id} — rename it, or switch it off and on. */
    public function update(Request $request, string $category): JsonResponse
    {
        $this->requirePermission($request, 'petty.cash.manage');
        $organisationId = $this->organisationId($request);
        $category = $this->categories($organisationId)->findOrFail($category);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'min:3', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (isset($data['name'])) {
            $data['name'] = trim($data['name']);
            $this->ensureNameIsFree($organisationId, $data['name'], $category->id);
        }
        if (($data['is_active'] ?? true) === false && $category->system_role !== null) {
            return $this->error('PROTECTED_CATEGORY', "{$category->name} is used by the system's own postings and cannot be switched off.", 409);
        }

        $before = ['name' => $category->name, 'is_active' => $category->is_active];
        $category->update($data);
        AuditLog::record('EXPENSE_CATEGORY_UPDATED', 'chart_of_account', $category->id, [
            'reference' => $category->code, 'before_json' => $before, 'after_json' => ['name' => $category->name, 'is_active' => $category->is_active],
        ]);

        return response()->json($this->present($category));
    }

    /** DELETE /api/finance/expense-categories/{id} — only one the institution added and never used. */
    public function destroy(Request $request, string $category): JsonResponse
    {
        $this->requirePermission($request, 'petty.cash.manage');
        $this->requirePermission($request, 'record.delete');
        $category = $this->categories($this->organisationId($request))->findOrFail($category);

        $blocker = $this->deleteBlocker($category);
        if ($blocker !== null) {
            return $this->error('CATEGORY_IN_USE', $blocker.' Switch it off instead so it is no longer offered.', 409);
        }

        AuditLog::record('EXPENSE_CATEGORY_DELETED', 'chart_of_account', $category->id, ['reference' => $category->code, 'before_json' => ['code' => $category->code, 'name' => $category->name]]);
        $category->delete();

        return response()->json(['deleted' => true]);
    }

    /**
     * @return Builder<ChartOfAccount>
     */
    private function categories(string $organisationId): Builder
    {
        return ChartOfAccount::where('organisation_id', $organisationId)
            ->where('account_type', 'EXPENSE')->where('is_postable', true)
            ->whereBetween('code', ['6000', '6999']);
    }

    /**
     * @return array{id: string, code: string, name: string, is_active: bool, is_standard: bool, is_protected: bool, times_used: int, can_delete: bool}
     */
    private function present(ChartOfAccount $category): array
    {
        $timesUsed = $this->timesUsed($category);

        return [
            'id' => $category->id,
            'code' => $category->code,
            'name' => $category->name,
            'is_active' => (bool) $category->is_active,
            'is_standard' => $this->isStandard($category),
            'is_protected' => $category->system_role !== null,
            'times_used' => $timesUsed,
            'can_delete' => $this->deleteBlocker($category, $timesUsed) === null,
        ];
    }

    private function deleteBlocker(ChartOfAccount $category, ?int $timesUsed = null): ?string
    {
        if ($category->system_role !== null) {
            return "{$category->name} is used by the system's own postings.";
        }
        if ($this->isStandard($category)) {
            return "{$category->name} is one of the standard categories.";
        }
        if (($timesUsed ?? $this->timesUsed($category)) > 0) {
            return "{$category->name} has already been used, so past vouchers and the ledger still point at it.";
        }

        return null;
    }

    private function isStandard(ChartOfAccount $category): bool
    {
        return in_array($category->code, array_column(ChartOfAccountsSeeder::ACCOUNTS, 0), true);
    }

    /** Vouchers and journal lines charged to the category, in any branch. */
    private function timesUsed(ChartOfAccount $category): int
    {
        return DB::table('petty_cash_vouchers')->where('account_id', $category->id)->count()
            + DB::table('journal_entry_lines')->where('account_id', $category->id)->count();
    }

    private function ensureNameIsFree(string $organisationId, string $name, ?string $exceptId = null): void
    {
        $taken = $this->categories($organisationId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['name' => 'There is already a category with that name.']);
        }
    }

    /** The first free code in tens from 6200 up (the standard ones sit below), then any free code at all. */
    private function nextFreeCode(string $organisationId): ?string
    {
        $taken = array_flip(ChartOfAccount::where('organisation_id', $organisationId)->whereBetween('code', ['6000', '6999'])->pluck('code')->all());
        $standard = array_flip(array_column(ChartOfAccountsSeeder::ACCOUNTS, 0));

        foreach ([range(6200, 6990, 10), range(6000, 6999)] as $candidates) {
            foreach ($candidates as $candidate) {
                $code = (string) $candidate;
                if (! isset($taken[$code]) && ! isset($standard[$code])) {
                    return $code;
                }
            }
        }

        return null;
    }
}
