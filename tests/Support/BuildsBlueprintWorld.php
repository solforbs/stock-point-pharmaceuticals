<?php

namespace Tests\Support;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerCredit;
use App\Models\CustomerTier;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\JournalEntryLine;
use App\Models\NumberSequence;
use App\Models\Organisation;
use App\Models\Permission;
use App\Models\PriceBreak;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductDiscountPolicy;
use App\Models\ProductPrice;
use App\Models\ProductUom;
use App\Models\Role;
use App\Models\RoleDiscountAuthority;
use App\Models\Sale;
use App\Models\StockBalance;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\TaxCode;
use App\Models\TaxRate;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Procurement\GoodsReceiptService;
use App\Services\Sales\CheckoutService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Builds the blueprint's worked-example world (Part 5.2 / V6 Part 9):
 * Amoxicillin 500mg with 1 CARTON = 10 BOXES = 200 STRIPS = 2,000 TABLETS,
 * one organisation/branch/store, the seeded chart of accounts, an open
 * financial period, a supplier and a wholesale customer.
 */
trait BuildsBlueprintWorld
{
    protected User $user;

    protected Organisation $org;

    protected Branch $branch;

    protected Store $store;

    protected Supplier $supplier;

    protected Customer $customer;

    protected Product $amox;

    /** @var array<string, UnitOfMeasure> keyed by code: TAB, STR, BOX, CTN */
    protected array $uoms = [];

    protected function buildWorld(): void
    {
        $this->user = User::create([
            'name' => 'Test Cashier',
            'username' => 'cashier',
            'email' => 'cashier@example.test',
            'password' => 'password-long-enough',
        ]);

        $this->org = Organisation::create([
            'name' => 'Stockpoint Pharma Wholesalers Ltd',
            'legal_name' => 'Stockpoint Pharma Wholesalers Ltd',
            'base_currency' => 'KES',
            'fiscal_year_start' => 1,
        ]);
        // Billing is not what these tests are about: the test institution is never lapsed.
        $this->org->forceFill(['is_complimentary' => true])->save();
        $this->user->forceFill(['organisation_id' => $this->org->id])->save();

        $this->branch = Branch::create([
            'organisation_id' => $this->org->id,
            'code' => 'LDW',
            'name' => 'Lodwar Main Branch',
            'retail_enabled' => true,
            'wholesale_enabled' => true,
        ]);

        $this->store = Store::create([
            'branch_id' => $this->branch->id,
            'code' => 'MAIN',
            'name' => 'Main Warehouse',
            'store_type' => 'MAIN',
            'is_sellable' => true,
        ]);

        (new ChartOfAccountsSeeder)->run();

        foreach (['TAB' => 'Tablet', 'STR' => 'Strip', 'BOX' => 'Box', 'CTN' => 'Carton'] as $code => $name) {
            $this->uoms[$code] = UnitOfMeasure::create(['code' => $code, 'name' => $name, 'is_base_candidate' => $code === 'TAB']);
        }

        $this->amox = Product::create([
            'organisation_id' => $this->org->id,
            'code' => 'AMOX500',
            'name' => 'Amoxicillin 500mg Capsules',
            'generic_name' => 'Amoxicillin',
            'strength' => '500mg',
            'base_uom_id' => $this->uoms['TAB']->id,
            'is_discrete' => true,
            'pack_integrity' => false,
            'requires_batch' => true,
            'default_price' => '2.5000',
            'is_active' => true,
        ]);

        foreach (['TAB' => 1, 'STR' => 10, 'BOX' => 200, 'CTN' => 2000] as $code => $factor) {
            ProductUom::create([
                'product_id' => $this->amox->id,
                'uom_id' => $this->uoms[$code]->id,
                'factor_to_base' => $factor,
                'is_base' => $factor === 1,
                'is_purchase' => in_array($code, ['BOX', 'CTN'], true),
                'is_sales' => true,
                'is_default_sales' => $code === 'BOX',
            ]);
        }

        $this->supplier = Supplier::create([
            'organisation_id' => $this->org->id,
            'code' => 'PDL',
            'name' => 'Pharma Distributors Ltd',
            'status' => 'ACTIVE',
        ]);

        $this->customer = Customer::create([
            'organisation_id' => $this->org->id,
            'code' => 'TCRH',
            'name' => 'Turkana County Referral Hospital',
            'customer_type' => 'HOSPITAL',
            'payment_terms_days' => 30,
        ]);

        CustomerCredit::create([
            'customer_id' => $this->customer->id,
            'credit_limit' => '1000000.0000',
            'current_balance' => '0.0000',
        ]);
    }

    /**
     * Another person in the same institution (a witness, an approver, a
     * message recipient).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function colleague(array $attributes): User
    {
        $user = User::create($attributes);
        $user->forceFill(['organisation_id' => $this->org->id])->save();

        return $user;
    }

    protected function uom(string $code): ProductUom
    {
        return ProductUom::where('product_id', $this->amox->id)->where('uom_id', $this->uoms[$code]->id)->firstOrFail();
    }

    /**
     * Receives stock through a real (emergency, PO-less) goods receipt so the
     * batch and ledger rows come from GoodsReceiptService, not hand-typed.
     * Quantity and cost are in the given purchase UOM.
     */
    protected function receive(string $batchNumber, string $expiry, string $qty, string $unitCost, string $uomCode = 'TAB', bool $release = true, ?string $qtyAccepted = null): ProductBatch
    {
        $receipt = GoodsReceipt::create([
            'doc_number' => NumberSequence::next($this->org->id, 'GRN', $this->branch->id, 'GRN'),
            'supplier_id' => $this->supplier->id,
            'branch_id' => $this->branch->id,
            'store_id' => $this->store->id,
            'status' => 'DRAFT',
            'is_emergency' => true,
            'received_by' => $this->user->id,
        ]);

        GoodsReceiptLine::create([
            'goods_receipt_id' => $receipt->id,
            'product_id' => $this->amox->id,
            'uom_id' => $this->uoms[$uomCode]->id,
            'qty_ordered' => $qty,
            'qty_delivered' => $qty,
            'qty_accepted' => $qtyAccepted ?? $qty,
            'qty_rejected' => bcsub($qty, $qtyAccepted ?? $qty, 4),
            'batch_number' => $batchNumber,
            'expiry_date' => $expiry,
            'unit_cost' => $unitCost,
        ]);

        app(GoodsReceiptService::class)->post($receipt);

        $batch = ProductBatch::where('product_id', $this->amox->id)->where('batch_number', $batchNumber)->firstOrFail();

        if ($release) {
            $batch->update(['status' => 'RELEASED', 'qc_released_by' => $this->user->id, 'qc_released_at' => now()]);
        }

        return $batch;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  list<array{method: string, amount: string, reference?: ?string}>  $payments
     * @param  array<string, mixed>  $overrides
     */
    protected function checkout(array $lines, array $payments = [], array $overrides = []): Sale
    {
        return app(CheckoutService::class)->checkout(array_merge([
            'organisation_id' => $this->org->id,
            'branch_id' => $this->branch->id,
            'store_id' => $this->store->id,
            'sale_mode' => 'RETAIL',
            'user_id' => $this->user->id,
            'idempotency_key' => (string) Str::uuid(),
            'lines' => $lines,
            'payments' => $payments,
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    protected function saleLine(string $uomCode, string $qty, string $unitPrice, array $extra = []): array
    {
        return array_merge([
            'product_id' => $this->amox->id,
            'uom_id' => $this->uoms[$uomCode]->id,
            'qty' => $qty,
            'list_price' => $unitPrice,
            'unit_price' => $unitPrice,
        ], $extra);
    }

    protected function ledgerSum(?string $batchId = null): string
    {
        $query = DB::table('stock_ledgers')->where('product_id', $this->amox->id)->where('store_id', $this->store->id);
        if ($batchId) {
            $query->where('batch_id', $batchId);
        }

        return number_format((float) $query->sum('qty_base'), 4, '.', '');
    }

    protected function balance(string $batchId): StockBalance
    {
        return StockBalance::where('product_id', $this->amox->id)->where('batch_id', $batchId)->where('store_id', $this->store->id)->firstOrFail();
    }

    /**
     * Per-account net balance (debits minus credits), keyed by account code —
     * the trial balance in its simplest form.
     *
     * @return array<string, string>
     */
    protected function trialBalance(): array
    {
        $rows = ChartOfAccount::query()
            ->leftJoin('journal_entry_lines', 'journal_entry_lines.account_id', '=', 'chart_of_accounts.id')
            ->selectRaw('chart_of_accounts.code as code, COALESCE(SUM(debit_amount), 0) - COALESCE(SUM(credit_amount), 0) as net')
            ->groupBy('chart_of_accounts.code')
            ->orderBy('chart_of_accounts.code')
            ->get();

        $balances = [];
        foreach ($rows as $row) {
            $balances[$row->code] = number_format((float) $row->net, 4, '.', '');
        }

        return $balances;
    }

    /**
     * Part 4.5 — give the test user a role carrying a discount authority in
     * the test branch (Spatie teams pivot keyed on branch_id).
     */
    /**
     * Gives the test user a branch-scoped role carrying exactly these
     * permissions (creating the Permission rows if the seeder has not run).
     *
     * @param  list<string>  $permissions
     */
    /**
     * Removes every role assignment, in every branch. `$user->roles()->detach()`
     * cannot do this: with Spatie teams the relation is scoped to the team id
     * in context, which is null outside a request.
     */
    protected function revokeAllRoles(): void
    {
        DB::table(config('permission.table_names.model_has_roles'))
            ->where('model_type', $this->user->getMorphClass())
            ->where('model_id', $this->user->getKey())
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->user->unsetRelation('roles')->unsetRelation('permissions');
    }

    protected function grantPermissions(array $permissions, string $roleName = 'Test role'): Role
    {
        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $role = Role::firstOrCreate(['organisation_id' => $this->org->id, 'name' => $roleName, 'guard_name' => 'web', 'branch_id' => null]);
        $role->syncPermissions($permissions);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $this->user->assignRole($role);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->user->unsetRelation('roles')->unsetRelation('permissions');

        return $role;
    }

    protected function grantAuthority(string $line, string $header = '0', bool $mayOverrideFloor = false, string $roleName = 'Wholesale rep'): Role
    {
        $role = Role::firstOrCreate(['organisation_id' => $this->org->id, 'name' => $roleName, 'guard_name' => 'web', 'branch_id' => null]);
        RoleDiscountAuthority::updateOrCreate(['role_id' => $role->id], [
            'max_line_discount_pct' => $line,
            'max_header_discount_pct' => $header,
            'may_override_floor' => $mayOverrideFloor,
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $this->user->assignRole($role);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);

        return $role;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function discountPolicy(array $overrides = []): ProductDiscountPolicy
    {
        return ProductDiscountPolicy::updateOrCreate(['product_id' => $this->amox->id], array_merge([
            'discount_allowed' => true,
            'max_discount_pct' => '8.000',
            'min_margin_pct' => '6.000',
            'discount_approval_pct' => '5.000',
            'bonus_allowed' => true,
            'round_to' => 'FIFTY_CENTS',
        ], $overrides));
    }

    /** Part 4.5 worked example: Tier B list at 500/box with the 1/10/50/200 step breaks. */
    protected function tierBWithBreaks(string $tierMaxDiscount = '10.000'): void
    {
        $tier = CustomerTier::create(['organisation_id' => $this->org->id, 'code' => 'B', 'name' => 'Tier B', 'max_discount_pct' => $tierMaxDiscount]);
        $this->customer->update(['tier_id' => $tier->id]);

        $list = PriceList::create([
            'organisation_id' => $this->org->id, 'code' => 'WHOLESALE-TIER-B', 'name' => 'Tier B',
            'sale_mode' => 'WHOLESALE', 'tier_id' => $tier->id, 'effective_from' => now()->subMonth()->toDateString(), 'is_active' => true,
        ]);
        $row = ProductPrice::create([
            'price_list_id' => $list->id, 'product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id,
            'factor_type' => 'FIXED', 'unit_price' => '500.0000', 'effective_from' => now()->subMonth()->toDateString(),
        ]);
        foreach ([['1', '9', '500.0000'], ['10', '49', '480.0000'], ['50', '199', '462.0000'], ['200', null, '445.0000']] as [$min, $max, $price]) {
            PriceBreak::create(['product_price_id' => $row->id, 'min_qty' => $min, 'max_qty' => $max, 'unit_price' => $price, 'break_type' => 'STEP']);
        }
    }

    protected function vat16(): TaxCode
    {
        $code = TaxCode::create(['organisation_id' => $this->org->id, 'code' => 'VAT_STD', 'name' => 'VAT standard', 'tax_type' => 'VAT']);
        TaxRate::create(['tax_code_id' => $code->id, 'rate_pct' => '16.000', 'effective_from' => now()->subYear()->toDateString()]);
        $this->amox->update(['tax_code_id' => $code->id]);

        return $code;
    }

    /**
     * @return array<string, mixed>
     */
    protected function quoteRequest(array $lines, array $overrides = []): array
    {
        return array_merge([
            'organisation_id' => $this->org->id,
            'branch_id' => $this->branch->id,
            'store_id' => $this->store->id,
            'sale_mode' => 'WHOLESALE',
            'customer_id' => $this->customer->id,
            'user_id' => $this->user->id,
            'lines' => $lines,
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    protected function quoteLine(string $uomCode, string $qty, array $extra = []): array
    {
        return array_merge(['product_id' => $this->amox->id, 'uom_id' => $this->uoms[$uomCode]->id, 'quantity' => $qty], $extra);
    }

    protected function accountBalance(string $role): string
    {
        $account = ChartOfAccount::byRole($this->org->id, $role);
        $net = JournalEntryLine::where('account_id', $account->id)->selectRaw('SUM(debit_amount) - SUM(credit_amount) as net')->value('net');

        return number_format((float) $net, 4, '.', '');
    }
}
