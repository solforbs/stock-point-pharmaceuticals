<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Organisation;
use App\Services\Documents\PdfRenderer;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 13 — the business as it appears on every invoice, kept by the System
 * Administrator.
 */
class OrganisationDetailsTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->grantPermissions(['admin.settings']);
        Sanctum::actingAs($this->user);
    }

    public function test_the_real_kra_pin_is_saved_and_then_printed_on_invoices(): void
    {
        Organisation::where('id', $this->org->id)->update(['kra_pin' => 'P000000000X']);
        $this->getJson('/api/admin/organisation')->assertOk()->assertJsonPath('kra_pin_is_placeholder', true);

        $this->patchJson('/api/admin/organisation', [
            'kra_pin' => 'p051234567m',   // typed in lower case
            'legal_name' => 'Stockpoint Pharma Wholesalers Limited',
            'vat_number' => '0123456X',
            'vat_registered' => true,
        ])->assertOk()
            ->assertJsonPath('kra_pin', 'P051234567M')
            ->assertJsonPath('kra_pin_is_placeholder', false)
            ->assertJsonPath('vat_number', '0123456X');

        $organisation = Organisation::findOrFail($this->org->id);
        $this->assertSame('0123456X', $organisation->vat_number, 'the VAT number is actually stored, not silently dropped');
        $this->assertSame($this->user->id, $organisation->updated_by);

        // The PDF letterhead now carries it.
        $this->assertSame('P051234567M', app(PdfRenderer::class)->letterhead($this->branch->id)['kra_pin']);

        $audit = AuditLog::where('action', 'ORGANISATION_UPDATED')->firstOrFail();
        $this->assertSame('P000000000X', $audit->before_json['kra_pin']);
    }

    public function test_a_malformed_or_placeholder_pin_is_refused(): void
    {
        foreach (['12345', 'P05123456M', 'X051234567M', 'P051234567', 'P0512345678M'] as $bad) {
            $this->patchJson('/api/admin/organisation', ['kra_pin' => $bad])
                ->assertStatus(422)->assertJsonValidationErrors('kra_pin');
        }

        $this->patchJson('/api/admin/organisation', ['kra_pin' => 'P000000000X'])
            ->assertStatus(422)->assertJsonPath('error.code', 'PLACEHOLDER_PIN');

        // A sole proprietor's personal PIN is valid.
        $this->patchJson('/api/admin/organisation', ['kra_pin' => 'A001234567Z'])->assertOk();
    }

    public function test_only_the_system_administrator_may_change_business_details(): void
    {
        $this->revokeAllRoles();
        $this->grantPermissions(['sale.create'], 'Cashier');

        $this->getJson('/api/admin/organisation')->assertStatus(403);
        $this->patchJson('/api/admin/organisation', ['kra_pin' => 'P051234567M'])->assertStatus(403);
    }
}
