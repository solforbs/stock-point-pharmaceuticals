<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
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
