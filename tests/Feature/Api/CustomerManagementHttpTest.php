<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Organisation;
use App\Services\Tenancy\TenantContext;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

class CustomerManagementHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        Sanctum::actingAs($this->user);
    }

    public function test_updating_a_customer_requires_customer_manage_permission(): void
    {
        // User without customer.manage permission
        $this->grantPermissions(['sale.view']);

        $this->patchJson("/api/customers/{$this->customer->id}", [
            'name' => 'Updated Hospital Name',
        ])->assertStatus(403);
    }

    public function test_updating_customer_profile_details_succeeds_and_creates_audit_log(): void
    {
        $this->grantPermissions(['customer.manage', 'sale.view']);

        $res = $this->patchJson("/api/customers/{$this->customer->id}", [
            'name' => 'St. Joseph Mission Hospital',
            'phone' => '+254712345678',
            'email' => 'accounts@stjoseph.co.ke',
            'address' => 'P.O. Box 100, Lodwar',
            'customer_type' => 'HOSPITAL',
            'payment_terms_days' => 45,
            'fulfilment_policy' => 'COMPLETE',
            'is_active' => true,
        ])->assertOk();

        $res->assertJsonPath('name', 'St. Joseph Mission Hospital')
            ->assertJsonPath('phone', '+254712345678')
            ->assertJsonPath('email', 'accounts@stjoseph.co.ke')
            ->assertJsonPath('payment_terms_days', 45)
            ->assertJsonPath('customer_type', 'HOSPITAL');

        $this->customer->refresh();
        $this->assertSame('St. Joseph Mission Hospital', $this->customer->name);
        $this->assertSame('+254712345678', $this->customer->phone);
        $this->assertSame('accounts@stjoseph.co.ke', $this->customer->email);
        $this->assertSame(45, $this->customer->payment_terms_days);

        // Verify audit log
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'CUSTOMER_UPDATED',
            'entity_type' => 'customer',
            'entity_id' => $this->customer->id,
        ]);
    }

    public function test_cannot_update_customer_belonging_to_another_organisation(): void
    {
        $this->grantPermissions(['customer.manage', 'sale.view']);

        $otherOrg = Organisation::create([
            'code' => 'OTHER',
            'name' => 'Other Healthcare Group',
        ]);

        // Planted as that institution: signed in here, the write would be refused.
        $otherCustomer = app(TenantContext::class)->run($otherOrg->id, fn () => Customer::create([
            'organisation_id' => $otherOrg->id,
            'code' => 'OTHER-001',
            'name' => 'Foreign Clinic',
            'customer_type' => 'CLINIC',
        ]));

        $this->patchJson("/api/customers/{$otherCustomer->id}", [
            'name' => 'Attempted Cross-Tenant Update',
        ])->assertNotFound();
    }

    public function test_customer_update_validates_fields(): void
    {
        $this->grantPermissions(['customer.manage', 'sale.view']);

        $this->patchJson("/api/customers/{$this->customer->id}", [
            'email' => 'invalid-email-string',
            'customer_type' => 'INVALID_TYPE',
            'payment_terms_days' => 500, // max 365
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'customer_type', 'payment_terms_days']);
    }
}
