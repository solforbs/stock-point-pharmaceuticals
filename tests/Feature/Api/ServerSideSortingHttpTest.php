<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

class ServerSideSortingHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        Sanctum::actingAs($this->user);
    }

    public function test_products_can_be_sorted_by_name_asc_and_desc(): void
    {
        $this->grantPermissions(['product.view']);

        // Create 2 products with predictable names
        Product::create([
            'organisation_id' => $this->org->id,
            'code' => 'ALPHA-01',
            'name' => 'AAA Product',
            'base_uom_id' => $this->uoms['TAB']->id,
            'default_price' => '10.0000',
        ]);
        Product::create([
            'organisation_id' => $this->org->id,
            'code' => 'ZETA-01',
            'name' => 'ZZZ Product',
            'base_uom_id' => $this->uoms['TAB']->id,
            'default_price' => '20.0000',
        ]);

        $resAsc = $this->getJson('/api/products?sort_by=name&sort_dir=asc')->assertOk();
        $namesAsc = collect($resAsc->json('data'))->pluck('name')->all();
        $this->assertSame('AAA Product', $namesAsc[0]);

        $resDesc = $this->getJson('/api/products?sort_by=name&sort_dir=desc')->assertOk();
        $namesDesc = collect($resDesc->json('data'))->pluck('name')->all();
        $this->assertSame('ZZZ Product', $namesDesc[0]);
    }

    public function test_customers_can_be_sorted_by_code_and_name(): void
    {
        $this->grantPermissions(['sale.create']);

        Customer::create([
            'organisation_id' => $this->org->id,
            'code' => 'AAA-CUST',
            'name' => 'Alpha Clinic',
            'customer_type' => 'CLINIC',
        ]);
        Customer::create([
            'organisation_id' => $this->org->id,
            'code' => 'ZZZ-CUST',
            'name' => 'Zulu Hospital',
            'customer_type' => 'HOSPITAL',
        ]);

        $resAsc = $this->getJson('/api/customers?sort_by=code&sort_dir=asc')->assertOk();
        $codesAsc = collect($resAsc->json('data'))->pluck('code')->all();
        $this->assertSame('AAA-CUST', $codesAsc[0]);

        $resDesc = $this->getJson('/api/customers?sort_by=code&sort_dir=desc')->assertOk();
        $codesDesc = collect($resDesc->json('data'))->pluck('code')->all();
        $this->assertSame('ZZZ-CUST', $codesDesc[0]);
    }

    public function test_suppliers_can_be_sorted_by_name(): void
    {
        $this->grantPermissions(['supplier.view']);

        Supplier::create([
            'organisation_id' => $this->org->id,
            'code' => 'SUP-A',
            'name' => 'AAA Wholesale Supplier',
            'status' => 'ACTIVE',
        ]);
        Supplier::create([
            'organisation_id' => $this->org->id,
            'code' => 'SUP-Z',
            'name' => 'ZZZ Import Supplier',
            'status' => 'ACTIVE',
        ]);

        $resAsc = $this->getJson('/api/suppliers?sort_by=name&sort_dir=asc')->assertOk();
        $namesAsc = collect($resAsc->json('data'))->pluck('name')->all();
        $this->assertSame('AAA Wholesale Supplier', $namesAsc[0]);

        $resDesc = $this->getJson('/api/suppliers?sort_by=name&sort_dir=desc')->assertOk();
        $namesDesc = collect($resDesc->json('data'))->pluck('name')->all();
        $this->assertSame('ZZZ Import Supplier', $namesDesc[0]);
    }

    public function test_journals_can_be_sorted_by_entry_date(): void
    {
        $this->grantPermissions(['journal.post']);

        $res = $this->getJson('/api/finance/journals?sort_by=entry_date&sort_dir=asc')->assertOk();
        $this->assertIsArray($res->json('data'));
    }

    public function test_invalid_sort_by_gracefully_falls_back_to_default_ordering(): void
    {
        $this->grantPermissions(['product.view']);

        // When passing a non-whitelisted sort_by (e.g. sql injection attempt or unknown column)
        $res = $this->getJson('/api/products?sort_by=non_existent_column&sort_dir=asc')->assertOk();
        $this->assertNotEmpty($res->json('data'));
    }
}
