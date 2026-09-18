<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\TaxCode;
use Database\Seeders\DosageFormSeeder;
use Database\Seeders\StorageConditionSeeder;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 5 — catalogue maintenance: the category tree and the bulk product
 * attribute round trip (CSV export, edit, all-or-nothing import).
 */
class ProductCatalogueHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    private Product $para;

    private TaxCode $vat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->vat = $this->vat16();
        $this->amox->update(['tax_code_id' => null]);
        TaxCode::create(['organisation_id' => $this->org->id, 'code' => 'OLD_VAT', 'name' => 'Retired', 'tax_type' => 'VAT', 'is_active' => false]);
        $this->para = Product::create([
            'organisation_id' => $this->org->id, 'code' => 'PARA500', 'name' => 'Paracetamol 500mg Tablets',
            'base_uom_id' => $this->uoms['TAB']->id, 'reorder_point' => '100', 'lead_time_days' => 7, 'is_active' => true,
        ]);
        $this->grantPermissions(['product.view', 'product.edit']);
        Sanctum::actingAs($this->user);
    }

    public function test_categories_are_created_updated_and_listed_with_inactive_ones(): void
    {
        $parent = $this->postJson('/api/product-categories', ['code' => 'ANTI', 'name' => 'Anti-infectives'])->assertCreated()->assertJsonPath('is_active', true)->json();
        $child = $this->postJson('/api/product-categories', ['code' => 'ABX', 'name' => 'Antibiotics', 'parent_id' => $parent['id']])->assertCreated()->json();

        $this->postJson('/api/product-categories', ['code' => 'ANTI', 'name' => 'Duplicate'])->assertStatus(422)->assertJsonValidationErrors('code');
        $this->postJson('/api/product-categories', ['code' => 'X'])->assertStatus(422)->assertJsonValidationErrors('name');

        // A category can never become its own ancestor.
        $this->patchJson("/api/product-categories/{$parent['id']}", ['parent_id' => $child['id']])->assertStatus(422)->assertJsonPath('error.code', 'CATEGORY_CYCLE');
        $this->patchJson("/api/product-categories/{$parent['id']}", ['parent_id' => $parent['id']])->assertStatus(422)->assertJsonPath('error.code', 'CATEGORY_CYCLE');

        $this->patchJson("/api/product-categories/{$child['id']}", ['name' => 'Systemic antibiotics', 'is_active' => false])->assertOk()
            ->assertJsonPath('name', 'Systemic antibiotics')->assertJsonPath('is_active', false);

        $this->amox->update(['category_id' => $parent['id']]);
        $all = collect($this->getJson('/api/product-categories/all')->assertOk()->json())->keyBy('code');
        $this->assertFalse($all['ABX']['is_active']);
        $this->assertSame('ANTI', $all['ABX']['parent']['code']);
        $this->assertSame(1, $all['ANTI']['products_count']);
        // The picker lookup still offers only active categories.
        $this->assertNotContains('ABX', collect($this->getJson('/api/product-categories')->json())->pluck('code')->all());

        $this->assertSame(1, AuditLog::where('action', 'PRODUCT_CATEGORY_UPDATED')->count());
        $this->assertSame(2, AuditLog::where('action', 'PRODUCT_CATEGORY_CREATED')->count());
    }

    public function test_import_updates_existing_products_and_blank_cells_leave_values_unchanged(): void
    {
        $category = ProductCategory::create(['code' => 'ANALG', 'name' => 'Analgesics']);

        $rows = [
            ['code' => 'PARA500', 'category_code' => 'analg', 'tax_code' => 'VAT_STD', 'reorder_point' => '250', 'safety_stock' => '1,000', 'lead_time_days' => '', 'generic_name' => 'Paracetamol', 'strength' => '', 'is_active' => ''],
            ['code' => 'AMOX500', 'category_code' => '', 'tax_code' => '', 'reorder_point' => '', 'safety_stock' => '', 'lead_time_days' => '14', 'generic_name' => '', 'strength' => '', 'is_active' => 'no'],
        ];

        $preview = $this->postJson('/api/products/import', ['rows' => $rows, 'dry_run' => true])->assertOk()
            ->assertJsonPath('dry_run', true)->assertJsonPath('updated', 2)->assertJsonPath('unchanged', 0)
            ->assertJsonPath('changes.0.code', 'PARA500')
            ->assertJsonPath('changes.0.fields.category_code.to', 'ANALG')
            ->assertJsonPath('changes.0.fields.tax_code.to', 'VAT_STD')
            ->json();
        $this->assertArrayNotHasKey('lead_time_days', $preview['changes'][0]['fields']);
        $this->assertNull($this->para->fresh()->category_id, 'a dry run writes nothing');

        $this->postJson('/api/products/import', ['rows' => $rows])->assertCreated()->assertJsonPath('updated', 2);

        $para = $this->para->fresh();
        $this->assertSame($category->id, $para->category_id);
        $this->assertSame($this->vat->id, $para->tax_code_id);
        $this->assertSame('250.0000', $para->reorder_point);
        $this->assertSame('1000.0000', $para->safety_stock);
        $this->assertSame(7, $para->lead_time_days);
        $this->assertSame('Paracetamol', $para->generic_name);
        $this->assertTrue($para->is_active);
        $this->assertSame($this->user->id, $para->updated_by);

        $amox = $this->amox->fresh();
        $this->assertSame(14, $amox->lead_time_days);
        $this->assertFalse($amox->is_active);
        $this->assertSame('500mg', $amox->strength);
        $this->assertSame('Amoxicillin', $amox->generic_name);

        // Re-importing the same file changes nothing.
        $this->postJson('/api/products/import', ['rows' => $rows])->assertCreated()->assertJsonPath('updated', 0)->assertJsonPath('unchanged', 2);

        $this->assertSame(2, data_get(AuditLog::where('action', 'PRODUCTS_IMPORTED')->orderBy('occurred_at')->firstOrFail(), 'after_json.updated'));
        $this->assertSame(2, AuditLog::where('action', 'PRODUCTS_IMPORTED')->count());
    }

    public function test_one_bad_row_rejects_the_whole_file_with_per_row_errors(): void
    {
        $rows = [
            ['code' => 'PARA500', 'reorder_point' => '300'],
            ['code' => 'NOPE', 'reorder_point' => '10'],
            ['code' => 'AMOX500', 'category_code' => 'MISSING', 'tax_code' => 'OLD_VAT', 'safety_stock' => '-1', 'lead_time_days' => '2.5', 'is_active' => 'maybe'],
            ['code' => 'PARA500'],
            ['code' => ''],
        ];

        $errors = $this->postJson('/api/products/import', ['rows' => $rows])->assertStatus(422)
            ->assertJsonPath('error.code', 'PRODUCT_IMPORT_INVALID')
            ->json('error.details.rows');

        $this->assertArrayNotHasKey('1', $errors);
        $this->assertStringContainsString('unknown product code NOPE', $errors['2'][0]);
        $this->assertCount(5, $errors['3']);
        $this->assertStringContainsString('unknown category code MISSING', $errors['3'][0]);
        $this->assertStringContainsString('tax code OLD_VAT is inactive', $errors['3'][1]);
        $this->assertStringContainsString('appears twice', $errors['4'][0]);
        $this->assertSame(['code is required'], $errors['5']);

        $this->assertSame('100.0000', $this->para->fresh()->reorder_point, 'nothing was written');
        $this->assertSame(0, AuditLog::where('action', 'PRODUCTS_IMPORTED')->count());

        $this->postJson('/api/products/import', ['rows' => []])->assertStatus(422)->assertJsonValidationErrors('rows');
    }

    public function test_missing_categories_are_created_only_when_asked(): void
    {
        $rows = [['code' => 'PARA500', 'category_code' => 'OTC'], ['code' => 'AMOX500', 'category_code' => 'otc']];

        $this->postJson('/api/products/import', ['rows' => $rows])->assertStatus(422);
        $this->postJson('/api/products/import', ['rows' => $rows, 'create_missing_categories' => true, 'dry_run' => true])->assertOk()->assertJsonPath('categories_created', ['OTC']);
        $this->assertFalse(ProductCategory::where('code', 'OTC')->exists());

        $this->postJson('/api/products/import', ['rows' => $rows, 'create_missing_categories' => true])->assertCreated()->assertJsonPath('categories_created', ['OTC']);

        $otc = ProductCategory::where('code', 'OTC')->sole();
        $this->assertSame($otc->id, $this->para->fresh()->category_id);
        $this->assertSame($otc->id, $this->amox->fresh()->category_id);
    }

    public function test_export_is_a_csv_that_re_imports_unchanged(): void
    {
        $this->para->update(['category_id' => ProductCategory::create(['code' => 'ANALG', 'name' => 'Analgesics'])->id, 'tax_code_id' => $this->vat->id]);

        $csv = $this->get('/api/products/export')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->streamedContent();
        $lines = array_map(fn (string $l) => str_getcsv($l, ',', '"', ''), preg_split('/\r?\n/', trim(ltrim($csv, "\xEF\xBB\xBF"))));

        $this->assertSame(['code', 'name', 'category_code', 'tax_code', 'default_price', 'reorder_point', 'safety_stock', 'lead_time_days', 'generic_name', 'strength', 'is_active'], $lines[0]);
        $this->assertSame(['AMOX500', 'Amoxicillin 500mg Capsules', '', '', '2.5', '0', '0', '0', 'Amoxicillin', '500mg', '1'], $lines[1]);
        $this->assertSame(['PARA500', 'Paracetamol 500mg Tablets', 'ANALG', 'VAT_STD', '', '100', '0', '7', '', '', '1'], $lines[2]);

        $rows = array_map(fn (array $cells) => array_combine($lines[0], $cells), array_slice($lines, 1));
        $this->postJson('/api/products/import', ['rows' => $rows])->assertCreated()->assertJsonPath('updated', 0)->assertJsonPath('unchanged', 2);
    }

    public function test_catalogue_maintenance_requires_product_edit(): void
    {
        $this->revokeAllRoles();
        $this->grantPermissions(['product.view'], 'Viewer');

        $this->postJson('/api/product-categories', ['code' => 'X', 'name' => 'X'])->assertForbidden();
        $this->patchJson('/api/product-categories/'.ProductCategory::create(['code' => 'Y', 'name' => 'Y'])->id, ['name' => 'Z'])->assertForbidden();
        $this->postJson('/api/products/import', ['rows' => [['code' => 'PARA500']]])->assertForbidden();
        $this->get('/api/products/export')->assertOk();
    }

    /**
     * Part 5.2 / 8.5 — the reference lists the product form depends on. Both
     * ship seeded, and a product can be given a dosage form and the storage
     * condition cold-chain monitoring measures it against.
     */
    public function test_dosage_forms_and_storage_conditions_are_listed_and_set_on_a_product(): void
    {
        (new DosageFormSeeder)->run();
        (new StorageConditionSeeder)->run();

        $forms = $this->getJson('/api/dosage-forms')->assertOk()->json();
        $conditions = $this->getJson('/api/storage-conditions')->assertOk()->json();

        $this->assertSame(count(DosageFormSeeder::FORMS), count($forms));
        $cold = collect($conditions)->firstWhere('code', 'COLD');
        $this->assertSame('2.00', $cold['min_temp_c']);
        $this->assertSame('8.00', $cold['max_temp_c']);
        $this->assertTrue($cold['requires_cold_chain']);

        $tablet = collect($forms)->firstWhere('code', 'TAB');
        $this->patchJson("/api/products/{$this->para->id}", ['dosage_form_id' => $tablet['id'], 'storage_condition_id' => $cold['id']])->assertOk();

        $this->para->refresh();
        $this->assertSame($tablet['id'], $this->para->dosage_form_id);
        $this->assertSame($cold['id'], $this->para->storage_condition_id);

        // Stores are told their range, so an excursion can be detected at all.
        $this->assertSame('AMBIENT', $this->store->fresh()->storageCondition->code);
    }
}
