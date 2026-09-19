<?php

namespace Tests\Feature\Api;

use App\Models\Store;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

class StoreManagementHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        Sanctum::actingAs($this->user);
    }

    public function test_updating_store_requires_admin_settings_permission(): void
    {
        $this->grantPermissions(['sale.view']);

        $this->patchJson("/api/admin/branches/{$this->branch->id}/stores/{$this->store->id}", [
            'name' => 'Updated Store Name',
        ])->assertStatus(403);
    }

    public function test_updating_store_details_succeeds_and_creates_audit_log(): void
    {
        $this->grantPermissions(['admin.settings']);

        $res = $this->patchJson("/api/admin/branches/{$this->branch->id}/stores/{$this->store->id}", [
            'name' => 'Main Retail Dispensary',
            'store_type' => 'RETAIL',
            'is_sellable' => true,
        ])->assertOk();

        $res->assertJsonPath('name', 'Main Retail Dispensary')
            ->assertJsonPath('store_type', 'RETAIL')
            ->assertJsonPath('is_sellable', true);

        $this->store->refresh();
        $this->assertSame('Main Retail Dispensary', $this->store->name);
        $this->assertSame('RETAIL', $this->store->store_type);
        $this->assertTrue($this->store->is_sellable);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'STORE_UPDATED',
            'entity_type' => 'store',
            'entity_id' => $this->store->id,
        ]);
    }

    public function test_updating_store_rejects_duplicate_code_within_same_branch(): void
    {
        $this->grantPermissions(['admin.settings']);

        // Create a second store in the same branch
        $secondStore = Store::create([
            'branch_id' => $this->branch->id,
            'code' => 'COLD-1',
            'name' => 'Cold Storage 1',
            'store_type' => 'COLD',
            'is_sellable' => false,
        ]);

        // Attempt to rename $this->store to 'COLD-1'
        $res = $this->patchJson("/api/admin/branches/{$this->branch->id}/stores/{$this->store->id}", [
            'code' => 'COLD-1',
        ])->assertStatus(422);

        $res->assertJsonValidationErrors(['code']);
    }

    public function test_updating_store_allows_keeping_same_code(): void
    {
        $this->grantPermissions(['admin.settings']);

        $res = $this->patchJson("/api/admin/branches/{$this->branch->id}/stores/{$this->store->id}", [
            'code' => $this->store->code,
            'name' => 'Renamed While Keeping Code',
        ])->assertOk();

        $res->assertJsonPath('name', 'Renamed While Keeping Code');
    }
}
