<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\TaxCodeSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 9.6 supplier master and Part 13 tax codes — the two pieces of
 * reference data the imported catalogue needs before it can be bought or
 * sold with VAT.
 */
class SupplierAndTaxCodeHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->grantPermissions(['supplier.view', 'supplier.manage', 'product.view', 'product.edit', 'sale.create']);
        Sanctum::actingAs($this->user);
    }

    public function test_a_supplier_can_be_created_listed_and_updated(): void
    {
        $supplier = $this->postJson('/api/suppliers', ['code' => 'MRL', 'name' => 'Medina Remedies Limited', 'payment_terms_days' => 30, 'bank_account' => '0123456789'])
            ->assertCreated()
            ->assertJsonPath('code', 'MRL')
            ->assertJsonPath('status', 'ACTIVE')
            ->assertJsonPath('currency', 'KES')
            ->json();

        $this->getJson('/api/suppliers?q=Medina')->assertOk()->assertJsonPath('data.0.id', $supplier['id'])->assertJsonPath('data.0.payable_balance', '0.0000');

        $this->patchJson("/api/suppliers/{$supplier['id']}", ['status' => 'SUSPENDED', 'licence_expiry' => '2027-06-30', 'bank_account' => '9876543210'])
            ->assertOk()
            ->assertJsonPath('status', 'SUSPENDED')
            ->assertJsonPath('licence_expiry', '2027-06-30T00:00:00.000000Z');

        // Part 19.2 — bank detail changes are audited by field name, never by value.
        $updated = AuditLog::where('action', 'SUPPLIER_UPDATED')->where('entity_id', $supplier['id'])->firstOrFail();
        $this->assertContains('bank_account', $updated->after_json['changed_fields']);
        $this->assertStringNotContainsString('9876543210', json_encode($updated->toArray()));
        $this->assertSame(1, AuditLog::where('action', 'SUPPLIER_CREATED')->where('entity_id', $supplier['id'])->count());

        $this->postJson('/api/suppliers', ['code' => 'MRL', 'name' => 'Duplicate'])->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_managing_suppliers_needs_its_own_permission(): void
    {
        $viewer = User::create(['name' => 'Viewer', 'username' => 'viewer', 'email' => 'viewer@example.test', 'password' => 'a-long-enough-password']);
        $viewer->forceFill(['is_active' => true])->save();
        Sanctum::actingAs($viewer);

        $this->postJson('/api/suppliers', ['code' => 'X', 'name' => 'X'])->assertStatus(403);
    }

    public function test_tax_codes_are_listed_with_the_rate_in_force_and_apply_to_a_quote_once_assigned(): void
    {
        (new TaxCodeSeeder)->run();

        $codes = $this->getJson('/api/tax-codes')->assertOk()->assertJsonCount(3)->json();
        $standard = collect($codes)->firstWhere('code', 'VAT_STD');
        $this->assertSame('16.000', $standard['rate_pct']);

        $this->patchJson("/api/products/{$this->amox->id}", ['tax_code_id' => $standard['id']])->assertOk();
        $this->getJson("/api/products/{$this->amox->id}")->assertOk()->assertJsonPath('tax_code.code', 'VAT_STD');

        $this->receive('L1', now()->addYears(2)->toDateString(), '100', '2.0000');
        $this->postJson('/api/pricing/quote', ['sale_mode' => 'RETAIL', 'store_id' => $this->store->id, 'lines' => [['product_id' => $this->amox->id, 'uom_id' => $this->uoms['BOX']->id, 'quantity' => '2']]])
            ->assertOk()
            ->assertJsonPath('lines.0.tax_code', 'VAT_STD')
            ->assertJsonPath('lines.0.tax_rate', '16.000');
    }
}
