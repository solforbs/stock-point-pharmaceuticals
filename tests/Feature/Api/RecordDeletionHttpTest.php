<?php

namespace Tests\Feature\Api;

use App\Models\AccountsReceivable;
use App\Models\AuditLog;
use App\Models\CustomerContact;
use App\Models\CustomerCredit;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 18.3 — an administrator may delete master data, but only while
 * nothing points at it. Transactions are never deletable.
 */
class RecordDeletionHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->grantPermissions(['record.delete', 'product.edit', 'customer.manage', 'supplier.manage', 'admin.users', 'admin.settings', 'stock.view']);
        Sanctum::actingAs($this->user);
    }

    public function test_an_unused_record_is_deleted_and_the_whole_row_is_kept_in_the_audit_log(): void
    {
        $category = ProductCategory::create(['code' => 'TYPO', 'name' => 'Typed by mistake', 'is_active' => true]);

        $this->getJson("/api/admin/records/product-categories/{$category->id}/references")->assertOk()
            ->assertJsonPath('deletable', true)
            ->assertJsonPath('references', []);

        $this->deleteJson("/api/admin/records/product-categories/{$category->id}")->assertOk()
            ->assertJsonPath('deleted', true)
            ->assertJsonPath('label', 'TYPO');

        $this->assertNull(ProductCategory::find($category->id));

        $audit = AuditLog::where('action', 'RECORD_DELETED')->where('entity_id', (string) $category->id)->firstOrFail();
        $this->assertSame('TYPO', $audit->before_json['code'], 'the deleted row is readable afterwards');
        $this->assertSame('Typed by mistake', $audit->before_json['name']);
    }

    public function test_a_record_something_points_at_is_refused_and_told_what_holds_it(): void
    {
        $category = ProductCategory::create(['code' => 'ABX', 'name' => 'Antibiotics', 'is_active' => true]);
        $this->amox->update(['category_id' => $category->id]);

        $this->getJson("/api/admin/records/product-categories/{$category->id}/references")->assertOk()
            ->assertJsonPath('deletable', false)
            ->assertJsonPath('references.0.table', 'products')
            ->assertJsonPath('references.0.count', 1);

        $this->deleteJson("/api/admin/records/product-categories/{$category->id}")->assertStatus(409)
            ->assertJsonPath('error.code', 'RECORD_IN_USE')
            ->assertJsonFragment(['message' => 'ABX cannot be deleted: it is used by 1 products. Deactivate it instead, so the documents that refer to it still make sense.']);

        $this->assertNotNull(ProductCategory::find($category->id));
    }

    public function test_a_product_that_has_been_received_or_sold_can_never_be_deleted(): void
    {
        $spare = Product::create([
            'organisation_id' => $this->org->id, 'code' => 'SPARE', 'name' => 'Never traded',
            'base_uom_id' => $this->uoms['TAB']->id, 'is_active' => true,
        ]);
        $this->deleteJson("/api/admin/records/products/{$spare->id}")->assertOk();

        // The fixture product has stock, batches and ledger rows behind it.
        $this->receive('B-KEEP', now()->addYear()->toDateString(), '100', '2.0000');
        $response = $this->deleteJson("/api/admin/records/products/{$this->amox->id}")->assertStatus(409);
        $tables = array_column($response->json('error.details.references'), 'table');
        $this->assertContains('stock_ledgers', $tables);
        $this->assertNotNull(Product::find($this->amox->id));
    }

    public function test_delete_all_products_clears_the_catalogue_but_keeps_products_with_history(): void
    {
        $spare = Product::create([
            'organisation_id' => $this->org->id, 'code' => 'SPARE', 'name' => 'Never traded',
            'base_uom_id' => $this->uoms['TAB']->id, 'is_active' => true,
        ]);
        $this->receive('B-KEEP', now()->addYear()->toDateString(), '100', '2.0000');

        $this->deleteJson('/api/products', ['confirm' => 'yes'])->assertUnprocessable()->assertJsonValidationErrors('confirm');
        $this->assertNotNull(Product::find($spare->id));

        $this->deleteJson('/api/products', ['confirm' => 'DELETE ALL PRODUCTS'])->assertOk()
            ->assertJsonPath('kept', 1);

        $this->assertNull(Product::find($spare->id));
        $this->assertNotNull(Product::find($this->amox->id), 'a product with stock behind it is kept');
        $this->assertSame(1, Product::count());
        $audit = AuditLog::where('action', 'RECORD_DELETED')->where('entity_id', $spare->id)->firstOrFail();
        $this->assertSame('SPARE', $audit->reference);
        $this->assertSame('Never traded', $audit->before_json['name'], 'the deleted row is readable afterwards');
        $this->assertSame($this->org->id, $audit->organisation_id);
    }

    public function test_delete_all_products_needs_the_delete_permission(): void
    {
        $this->revokeAllRoles();
        $this->grantPermissions(['product.edit'], 'Editor without delete');

        $this->deleteJson('/api/products', ['confirm' => 'DELETE ALL PRODUCTS'])->assertForbidden();
        $this->assertNotNull(Product::find($this->amox->id));
    }

    public function test_a_customer_is_deleted_with_its_settled_credit_account_and_contacts(): void
    {
        $customer = $this->postJson('/api/customers', ['code' => 'LN-54878', 'name' => 'Lenah Jones', 'customer_type' => 'RETAIL_PHARMACY', 'credit_limit' => '5000'])->assertCreated()->json();
        CustomerContact::create(['customer_id' => $customer['id'], 'name' => 'Lenah']);
        $this->assertTrue(CustomerCredit::whereKey($customer['id'])->exists());

        $this->getJson("/api/admin/records/customers/{$customer['id']}/references")->assertOk()->assertJsonPath('deletable', true);
        $this->deleteJson("/api/admin/records/customers/{$customer['id']}")->assertOk()->assertJsonPath('label', 'LN-54878');

        $this->assertFalse(CustomerCredit::whereKey($customer['id'])->exists(), 'the credit account goes with the customer');
        $this->assertSame(0, CustomerContact::where('customer_id', $customer['id'])->count());
        $audit = AuditLog::where('action', 'RECORD_DELETED')->where('entity_id', $customer['id'])->firstOrFail();
        $this->assertSame('5000.0000', $audit->before_json['deleted_with_it']['customer_credits'][0]['credit_limit']);
    }

    public function test_a_customer_is_deleted_with_its_statement_while_its_sales_stay_unnamed(): void
    {
        $customer = $this->postJson('/api/customers', ['code' => 'OWES', 'name' => 'Owes Money', 'customer_type' => 'CLINIC'])->assertCreated()->json();
        $this->receive('B-CUST', now()->addYear()->toDateString(), '100', '2.0000');
        $sale = $this->checkout([$this->saleLine('TAB', '10', '5.00')], [['method' => 'CASH', 'amount' => '50.00']], ['customer_id' => $customer['id']]);
        AccountsReceivable::create(['organisation_id' => $this->org->id, 'customer_id' => $customer['id'], 'txn_type' => 'INVOICE', 'sale_id' => $sale->id, 'amount' => '1250', 'balance_after' => '1250', 'branch_id' => $this->branch->id]);
        CustomerCredit::whereKey($customer['id'])->update(['current_balance' => '1250']);

        $this->getJson("/api/admin/records/customers/{$customer['id']}/references")->assertOk()->assertJsonPath('deletable', true);
        $this->deleteJson("/api/admin/records/customers/{$customer['id']}")->assertOk();

        $this->assertSame(0, AccountsReceivable::where('customer_id', $customer['id'])->count(), 'the statement of account goes with the customer');
        $this->assertFalse(CustomerCredit::whereKey($customer['id'])->exists());
        $this->assertNull($sale->fresh()->customer_id, 'the sale stays in the books, without a name');
        $audit = AuditLog::where('action', 'RECORD_DELETED')->where('entity_id', $customer['id'])->firstOrFail();
        $this->assertCount(1, $audit->before_json['deleted_with_it']['accounts_receivables']);
        $this->assertSame([$sale->id], $audit->before_json['detached']['sales']);
    }

    public function test_transactions_are_not_a_deletable_type_at_all(): void
    {
        foreach (['sales', 'journal-entries', 'stock-ledgers', 'payments'] as $type) {
            $this->deleteJson("/api/admin/records/{$type}/01a0b000-0000-7000-8000-000000000000")
                ->assertStatus(404)
                ->assertJsonPath('error.message', "There is no deletable record type '{$type}'. Transactions are voided or reversed, never deleted.");
        }
    }

    public function test_an_administrator_cannot_delete_their_own_account_or_the_last_one_that_manages_users(): void
    {
        $this->deleteJson("/api/admin/records/users/{$this->user->id}")->assertStatus(422)
            ->assertJsonPath('error.message', 'You cannot delete the account you are signed in with.');

        $other = User::create([
            'name' => 'Second Admin', 'username' => 'second', 'email' => 'second@stockpoint.test',
            'password' => Hash::make('a-long-enough-password'), 'is_active' => true,
        ]);

        // Nobody else can manage users, so this one may go.
        $this->deleteJson("/api/admin/records/users/{$other->id}")->assertOk()->assertJsonPath('deleted', true);
        $this->assertNull(User::find($other->id));
    }

    public function test_deleting_needs_both_the_delete_right_and_the_right_for_that_area(): void
    {
        $category = ProductCategory::create(['code' => 'TMP', 'name' => 'Temporary', 'is_active' => true]);

        $this->revokeAllRoles();
        $this->grantPermissions(['product.edit'], 'Editor without delete');
        $this->deleteJson("/api/admin/records/product-categories/{$category->id}")->assertStatus(403);

        $this->revokeAllRoles();
        $this->grantPermissions(['record.delete'], 'Delete without product rights');
        $this->deleteJson("/api/admin/records/product-categories/{$category->id}")->assertStatus(403);

        $this->assertNotNull(ProductCategory::find($category->id));
    }
}
