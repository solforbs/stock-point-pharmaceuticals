<?php

namespace Tests\Feature\Api;

use App\Models\Licence;
use App\Models\Organisation;
use App\Models\Supplier;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

class SingleRecordShowHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        Sanctum::actingAs($this->user);
    }

    public function test_can_fetch_single_supplier_by_id(): void
    {
        $this->grantPermissions(['supplier.view']);

        $res = $this->getJson("/api/suppliers/{$this->supplier->id}")
            ->assertOk()
            ->assertJsonPath('id', $this->supplier->id)
            ->assertJsonPath('code', $this->supplier->code)
            ->assertJsonPath('name', $this->supplier->name);

        $this->assertArrayHasKey('payable_balance', $res->json());
    }

    public function test_cannot_fetch_supplier_belonging_to_another_organisation(): void
    {
        $this->grantPermissions(['supplier.view']);

        $otherOrg = Organisation::create([
            'code' => 'OTHER',
            'name' => 'Other Healthcare Group',
        ]);

        $otherSupplier = Supplier::create([
            'organisation_id' => $otherOrg->id,
            'code' => 'OTH-SUPP',
            'name' => 'Other Supplier',
        ]);

        $this->getJson("/api/suppliers/{$otherSupplier->id}")->assertNotFound();
    }

    public function test_can_fetch_single_admin_user_by_id(): void
    {
        $this->grantPermissions(['admin.users']);

        $this->getJson("/api/admin/users/{$this->user->id}")
            ->assertOk()
            ->assertJsonPath('id', $this->user->id)
            ->assertJsonPath('email', $this->user->email)
            ->assertJsonStructure(['id', 'name', 'email', 'assignments']);
    }

    public function test_can_fetch_single_licence_and_supplier_licence(): void
    {
        $this->grantPermissions(['licence.view']);

        $licence = Licence::create([
            'organisation_id' => $this->org->id,
            'holder_type' => 'ORGANISATION',
            'holder_id' => $this->org->id,
            'licence_type' => 'BUSINESS_PERMIT',
            'licence_number' => 'BP-2026-001',
            'issued_by' => 'County Government of Turkana',
            'expiry_date' => now()->addYear()->toDateString(),
            'is_active' => true,
        ]);

        // Native licence
        $this->getJson("/api/licences/{$licence->id}")
            ->assertOk()
            ->assertJsonPath('id', $licence->id)
            ->assertJsonPath('licence_number', 'BP-2026-001')
            ->assertJsonPath('source', 'licence')
            ->assertJsonPath('read_only', false);

        // Synthetic supplier licence
        $this->supplier->update([
            'licence_number' => 'PPB-SUPP-99',
            'licence_expiry' => now()->addMonths(6)->toDateString(),
        ]);

        $this->getJson("/api/licences/supplier:{$this->supplier->id}")
            ->assertOk()
            ->assertJsonPath('id', "supplier:{$this->supplier->id}")
            ->assertJsonPath('licence_number', 'PPB-SUPP-99')
            ->assertJsonPath('source', 'supplier')
            ->assertJsonPath('read_only', true);
    }
}
