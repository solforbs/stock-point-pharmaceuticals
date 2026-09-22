<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\DosageForm;
use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\NumberSequence;
use App\Models\Organisation;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\ProductUom;
use App\Models\Role;
use App\Models\Sale;
use App\Models\StorageCondition;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Procurement\GoodsReceiptService;
use App\Services\Sales\CheckoutService;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * One database, many institutions: nothing a signed-in user sends can read,
 * reference, change or collide with another institution's data.
 *
 * Institution A is the standard test world (Stockpoint, Lodwar); institution
 * B ("Nairobi Chemists") is built beside it with its own branch, store,
 * product, customer, supplier, user, stock and a posted sale. Every test
 * signs in as A and tries to reach B.
 */
class TenantIsolationTest extends TestCase
{
    use BuildsBlueprintWorld;

    private Organisation $orgB;

    private Branch $branchB;

    private Store $storeB;

    private Product $productB;

    private Customer $customerB;

    private Supplier $supplierB;

    private User $userB;

    private ProductBatch $batchB;

    private Sale $saleB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->receive('B-A1', now()->addYear()->toDateString(), '1000', '2.0000');
        $this->grantPermissions([
            'product.view', 'product.create', 'product.edit', 'stock.view', 'sale.view', 'sale.create', 'customer.manage',
            'supplier.view', 'po.create', 'return.create', 'stock.transfer.create', 'admin.users', 'admin.settings',
            'audit.view', 'record.delete', 'message.send',
        ]);
        $this->buildRivalInstitution();
        Sanctum::actingAs($this->user);
    }

    public function test_lists_show_only_the_signed_in_institutions_records(): void
    {
        $this->assertSame(['AMOX500'], collect($this->getJson('/api/products')->assertOk()->json('data'))->pluck('code')->all());
        $this->assertSame([$this->customer->id], collect($this->getJson('/api/customers')->assertOk()->json('data'))->pluck('id')->all());
        $this->assertNotContains($this->supplierB->id, collect($this->getJson('/api/suppliers')->assertOk()->json('data'))->pluck('id')->all());
        $this->assertNotContains($this->storeB->id, collect($this->getJson('/api/stores')->assertOk()->json())->pluck('id')->all());
        $this->assertNotContains($this->saleB->id, collect($this->getJson('/api/sales')->assertOk()->json('data'))->pluck('id')->all());
        $this->assertNotContains($this->userB->id, collect($this->getJson('/api/admin/users')->assertOk()->json('data'))->pluck('id')->all());
        $this->assertNotContains($this->userB->id, collect($this->getJson('/api/users')->assertOk()->json())->pluck('id')->all());

        $roles = collect($this->getJson('/api/admin/roles')->assertOk()->json());
        $this->assertSame(1, $roles->where('name', 'Cashier')->count(), 'A sees its own Cashier, never B\'s as well');

        $audit = collect($this->getJson('/api/admin/audit-log?all_branches=1')->assertOk()->json('data'));
        $this->assertFalse($audit->contains('branch_id', $this->branchB->id), 'even "all branches" means all of this institution\'s');
    }

    public function test_another_institutions_record_is_not_found_by_id(): void
    {
        $this->getJson("/api/products/{$this->productB->id}")->assertNotFound();
        $this->getJson("/api/products/{$this->productB->id}/stock")->assertNotFound();
        $this->getJson("/api/products/{$this->productB->id}/insight?store_id={$this->store->id}")->assertNotFound();
        $this->getJson("/api/customers/{$this->customerB->id}")->assertNotFound();
        $this->getJson("/api/sales/{$this->saleB->id}")->assertNotFound();
        $this->get("/api/sales/{$this->saleB->id}/pdf")->assertNotFound();
        $this->getJson("/api/admin/users/{$this->userB->id}")->assertNotFound();
        $this->patchJson("/api/admin/users/{$this->userB->id}", ['is_active' => false])->assertNotFound();
        $this->deleteJson("/api/admin/records/products/{$this->productB->id}")->assertNotFound();
        $this->deleteJson("/api/admin/records/users/{$this->userB->id}")->assertNotFound();

        $this->assertTrue($this->userB->fresh()->is_active, 'B\'s user was not touched');
        $this->assertNotNull(Product::withoutGlobalScopes()->find($this->productB->id), 'B\'s product was not deleted');
    }

    public function test_writes_cannot_reference_another_institutions_records(): void
    {
        $this->postJson('/api/pricing/quote', [
            'sale_mode' => 'RETAIL', 'store_id' => $this->storeB->id,
            'lines' => [['product_id' => $this->productB->id, 'uom_id' => $this->uoms['TAB']->id, 'quantity' => '1']],
        ])->assertStatus(422)->assertJsonValidationErrors(['store_id', 'lines.0.product_id']);

        $this->postJson('/api/inventory/transfers', [
            'from_store_id' => $this->store->id, 'to_store_id' => $this->storeB->id,
            'lines' => [['product_id' => $this->amox->id, 'batch_id' => $this->batchB->id, 'qty_base' => '1']],
        ])->assertStatus(422)->assertJsonValidationErrors(['to_store_id', 'lines.0.batch_id']);

        $this->postJson('/api/purchase-orders', [
            'supplier_id' => $this->supplierB->id,
            'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'qty_ordered' => '1', 'unit_price' => '1']],
        ])->assertStatus(422)->assertJsonValidationErrors('supplier_id');

        $this->postJson('/api/quotations', [
            'customer_id' => $this->customerB->id, 'store_id' => $this->store->id, 'valid_until' => now()->addWeek()->toDateString(),
            'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'qty' => '1']],
        ])->assertStatus(422)->assertJsonValidationErrors('customer_id');

        $this->postJson('/api/customer-returns', [
            'sale_id' => $this->saleB->id, 'store_id' => $this->store->id, 'reason' => 'Trying another institution\'s sale',
            'lines' => [['sale_line_id' => $this->saleB->lines()->first()->id, 'batch_id' => $this->batchB->id, 'qty_base' => '1']],
        ])->assertStatus(422)->assertJsonValidationErrors(['sale_id', 'lines.0.batch_id']);

        $this->postJson('/api/admin/users', [
            'name' => 'Sneaky', 'username' => 'sneaky', 'email' => 'sneaky@example.test', 'password' => 'a-long-enough-password',
            'assignments' => [['branch_id' => $this->branchB->id, 'roles' => ['Cashier']]],
        ])->assertStatus(422)->assertJsonValidationErrors('assignments.0.branch_id');

        $this->postJson('/api/messages', ['recipient_id' => $this->userB->id, 'body' => 'Hello from another institution'])
            ->assertStatus(422)->assertJsonValidationErrors('recipient_id');
    }

    public function test_services_refuse_a_foreign_record_even_past_validation(): void
    {
        // The model layer is the second line of defence: a lookup by id
        // inside a request never finds another institution's row, and a row
        // cannot be written into another institution.
        $this->getJson('/api/products')->assertOk();

        $this->assertNull(Store::find($this->storeB->id));
        $this->assertNull(Sale::find($this->saleB->id));
        $this->assertNull(ProductBatch::find($this->batchB->id));

        $this->expectException(AuthorizationException::class);
        Customer::create(['organisation_id' => $this->orgB->id, 'code' => 'X', 'name' => 'Planted', 'customer_type' => 'RETAIL']);
    }

    public function test_roles_are_per_institution_and_the_permission_cache_serves_both(): void
    {
        $cashierA = collect($this->getJson('/api/admin/roles')->json())->firstWhere('name', 'Cashier');
        $this->patchJson("/api/admin/roles/{$cashierA['id']}", ['permissions' => ['sale.view']])->assertOk();

        $cashierB = Role::withoutGlobalScopes()->where('organisation_id', $this->orgB->id)->where('name', 'Cashier')->firstOrFail();
        $this->assertTrue($cashierB->hasPermissionTo('sale.create'), 'A editing its Cashier left B\'s Cashier alone');
        $this->patchJson("/api/admin/roles/{$cashierB->id}", ['permissions' => []])->assertNotFound();

        // Spatie caches every permission with the roles holding it. Warmed by
        // A, the cache must still carry B's roles, or B would be denied.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->getJson('/api/products')->assertOk();

        Sanctum::actingAs($this->userB);
        $this->assertSame(['AMOX500'], collect($this->getJson('/api/products')->assertOk()->json('data'))->pluck('code')->all());
        $this->assertSame($this->productB->id, $this->getJson('/api/products')->json('data.0.id'), 'B sees its own product of the same code');
    }

    public function test_codes_and_document_numbers_are_unique_per_institution_not_globally(): void
    {
        // B's product shares A's code and manufacturer barcode.
        $this->assertSame($this->amox->code, $this->productB->code);

        $saleA = $this->checkout([$this->saleLine('TAB', '1', '2.5000')], [['method' => 'CASH', 'amount' => '2.5000']]);
        $this->assertSame($this->saleB->doc_number, $saleA->doc_number, 'each institution numbers its own documents from 1');

        $this->postJson('/api/products', [
            'code' => 'NEW1', 'name' => 'New product', 'base_uom_id' => $this->uoms['TAB']->id, 'sku' => 'SKU-B',
        ])->assertCreated();
        $this->assertSame(2, Product::withoutGlobalScopes()->where('sku', 'SKU-B')->count(), 'the same SKU in two institutions');
    }

    public function test_shared_reference_lists_are_read_only_and_private_additions_stay_private(): void
    {
        // Planted directly: signed in as A, the models would stamp both with A.
        $sharedId = (string) Str::uuid();
        DB::table('storage_conditions')->insert(['id' => $sharedId, 'organisation_id' => null, 'code' => 'COLD', 'name' => 'Cold chain (2–8 °C)', 'requires_cold_chain' => true, 'created_at' => now(), 'updated_at' => now()]);
        $privateId = (string) Str::uuid();
        DB::table('dosage_forms')->insert(['id' => $privateId, 'organisation_id' => $this->orgB->id, 'code' => 'B-ONLY', 'name' => 'B\'s private form', 'created_at' => now(), 'updated_at' => now()]);

        $conditions = collect($this->getJson('/api/storage-conditions')->assertOk()->json())->pluck('code');
        $this->assertContains('COLD', $conditions->all(), 'the shared list is everyone\'s');
        $this->assertNotContains('B-ONLY', collect($this->getJson('/api/dosage-forms')->assertOk()->json())->pluck('code')->all());

        $this->deleteJson("/api/admin/records/storage-conditions/{$sharedId}")->assertForbidden();
        $this->deleteJson("/api/admin/records/dosage-forms/{$privateId}")->assertNotFound();
        $this->assertNotNull(StorageCondition::withoutGlobalScopes()->find($sharedId));
        $this->assertNotNull(DosageForm::withoutGlobalScopes()->find($privateId));
    }

    public function test_an_account_without_an_institution_cannot_open_institution_data(): void
    {
        $orphan = User::create(['name' => 'Orphan', 'username' => 'orphan', 'email' => 'orphan@example.test', 'password' => 'a-long-enough-password']);
        DB::table('users')->where('id', $orphan->id)->update(['organisation_id' => null]);

        Sanctum::actingAs($orphan->fresh());
        $this->getJson('/api/products')->assertForbidden()->assertJsonPath('error.code', 'NO_INSTITUTION');
    }

    public function test_platform_operations_need_a_platform_administrator(): void
    {
        $this->getJson('/api/admin/backups')->assertForbidden();

        $this->user->forceFill(['is_platform_admin' => true])->save();
        $this->getJson('/api/admin/backups')->assertOk();
    }

    /** Institution B, complete enough to be worth attacking. */
    private function buildRivalInstitution(): void
    {
        $this->orgB = Organisation::create(['name' => 'Nairobi Chemists Ltd', 'legal_name' => 'Nairobi Chemists Ltd', 'base_currency' => 'KES', 'fiscal_year_start' => 1]);
        ChartOfAccountsSeeder::provision($this->orgB->id);
        $this->branchB = Branch::create(['organisation_id' => $this->orgB->id, 'code' => 'LDW', 'name' => 'Nairobi CBD', 'retail_enabled' => true, 'wholesale_enabled' => true]);
        $this->storeB = Store::create(['branch_id' => $this->branchB->id, 'code' => 'MAIN', 'name' => 'Main', 'store_type' => 'MAIN', 'is_sellable' => true]);

        $this->productB = Product::create([
            'organisation_id' => $this->orgB->id, 'code' => 'AMOX500', 'sku' => 'SKU-B', 'name' => 'Amoxicillin 500mg (B)',
            'generic_name' => 'Amoxicillin', 'base_uom_id' => $this->uoms['TAB']->id, 'is_discrete' => true, 'requires_batch' => true,
            'default_price' => '3.0000', 'is_active' => true,
        ]);
        $sharedBarcode = ProductUom::where('product_id', $this->amox->id)->where('uom_id', $this->uoms['TAB']->id)->value('barcode') ?? '6001234567890';
        ProductUom::where('product_id', $this->amox->id)->where('uom_id', $this->uoms['TAB']->id)->update(['barcode' => $sharedBarcode]);
        ProductUom::create([
            'product_id' => $this->productB->id, 'uom_id' => $this->uoms['TAB']->id, 'factor_to_base' => 1, 'barcode' => $sharedBarcode,
            'is_base' => true, 'is_purchase' => true, 'is_sales' => true, 'is_default_sales' => true,
        ]);

        $this->customerB = Customer::create(['organisation_id' => $this->orgB->id, 'code' => 'TCRH', 'name' => 'B Customer', 'customer_type' => 'HOSPITAL']);
        $this->supplierB = Supplier::create(['organisation_id' => $this->orgB->id, 'code' => 'PDL', 'name' => 'B Supplier', 'status' => 'ACTIVE']);

        $this->userB = User::create(['name' => 'B Admin', 'username' => 'badmin', 'email' => 'badmin@example.test', 'password' => 'a-long-enough-password']);
        $this->userB->forceFill(['organisation_id' => $this->orgB->id])->save();

        // Both institutions get the standard role names; B's Cashier can sell.
        foreach ([[$this->org->id, 'Cashier'], [$this->orgB->id, 'Cashier']] as [$orgId, $name]) {
            Role::firstOrCreate(['organisation_id' => $orgId, 'name' => $name, 'guard_name' => 'web', 'branch_id' => null])
                ->syncPermissions(['sale.view', 'sale.create', 'product.view', 'stock.view']);
        }
        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($this->branchB->id);
        $this->userB->assignRole(Role::withoutGlobalScopes()->where('organisation_id', $this->orgB->id)->where('name', 'Cashier')->firstOrFail());
        $registrar->setPermissionsTeamId(null);
        $registrar->forgetCachedPermissions();

        $receipt = GoodsReceipt::create([
            'doc_number' => NumberSequence::next($this->orgB->id, 'GRN', $this->branchB->id, 'GRN'),
            'supplier_id' => $this->supplierB->id, 'branch_id' => $this->branchB->id, 'store_id' => $this->storeB->id,
            'status' => 'DRAFT', 'is_emergency' => true, 'received_by' => $this->userB->id,
        ]);
        GoodsReceiptLine::create([
            'goods_receipt_id' => $receipt->id, 'product_id' => $this->productB->id, 'uom_id' => $this->uoms['TAB']->id,
            'qty_ordered' => '100', 'qty_delivered' => '100', 'qty_accepted' => '100', 'qty_rejected' => '0',
            'batch_number' => 'B-B1', 'expiry_date' => now()->addYear()->toDateString(), 'unit_cost' => '1.0000',
        ]);
        app(GoodsReceiptService::class)->post($receipt);
        $this->batchB = ProductBatch::where('product_id', $this->productB->id)->firstOrFail();
        $this->batchB->update(['status' => 'RELEASED', 'qc_released_by' => $this->userB->id, 'qc_released_at' => now()]);

        $this->saleB = app(CheckoutService::class)->checkout([
            'organisation_id' => $this->orgB->id, 'branch_id' => $this->branchB->id, 'store_id' => $this->storeB->id,
            'sale_mode' => 'RETAIL', 'user_id' => $this->userB->id, 'idempotency_key' => (string) Str::uuid(),
            'lines' => [['product_id' => $this->productB->id, 'uom_id' => $this->uoms['TAB']->id, 'qty' => '1', 'list_price' => '3.0000', 'unit_price' => '3.0000']],
            'payments' => [['method' => 'CASH', 'amount' => '3.0000']],
        ]);
    }
}
