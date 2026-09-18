<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * V6 Part 16.3 — the licence register: expiry status derived from dates,
 * supplier licences listed read-only, documents attached, archive not delete.
 */
class LicenceHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        Storage::fake('local');
        $this->grantPermissions(['licence.view', 'licence.manage']);
        Sanctum::actingAs($this->user);
    }

    public function test_licences_are_registered_with_a_document_and_listed_with_supplier_licences(): void
    {
        $this->supplier->update(['licence_number' => 'PPB/WHL/0091', 'licence_expiry' => now()->subDays(3)->toDateString()]);

        $premises = $this->post('/api/licences', [
            'holder_type' => 'BRANCH', 'holder_id' => $this->branch->id, 'licence_type' => 'PPB_PREMISES',
            'licence_number' => 'PPB/PREM/2026/118', 'issued_by' => 'Pharmacy and Poisons Board',
            'issue_date' => now()->subYear()->toDateString(), 'expiry_date' => now()->addDays(45)->toDateString(),
            'document' => UploadedFile::fake()->create('premises.pdf', 300, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('status', 'EXPIRING')
            ->assertJsonPath('has_document', true)
            ->assertJsonMissingPath('document_path')
            ->json();

        $this->postJson('/api/licences', [
            'holder_type' => 'ORGANISATION', 'licence_type' => 'KRA_TCC', 'licence_number' => 'TCC-99812',
            'expiry_date' => now()->addYear()->toDateString(),
        ])->assertCreated()->assertJsonPath('status', 'VALID')->assertJsonPath('holder_id', $this->org->id);

        $this->getJson('/api/licences')->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('summary.expired', 1)
            ->assertJsonPath('summary.expiring', 1)
            ->assertJsonPath('summary.valid', 1)
            ->assertJsonPath('data.0.source', 'supplier')
            ->assertJsonPath('data.0.read_only', true)
            ->assertJsonPath('data.0.status', 'EXPIRED')
            ->assertJsonPath('data.1.holder_name', 'Lodwar Main Branch');

        $this->getJson('/api/licences?holder_type=SUPPLIER')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/licences?status=VALID')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.licence_number', 'TCC-99812');
        $this->getJson('/api/licences?q=prem/2026')->assertOk()->assertJsonCount(1, 'data');

        $this->get("/api/licences/{$premises['id']}/document")->assertOk()->assertDownload('premises.pdf');

        // Renewal: new expiry and replacement document through multipart POST.
        $this->post("/api/licences/{$premises['id']}", [
            'expiry_date' => now()->addYears(2)->toDateString(),
            'document' => UploadedFile::fake()->create('renewed.pdf', 100, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('status', 'VALID');
        $this->get("/api/licences/{$premises['id']}/document")->assertOk()->assertDownload('renewed.pdf');

        // Archive instead of delete.
        $this->patchJson("/api/licences/{$premises['id']}", ['is_active' => false])->assertOk()->assertJsonPath('is_active', false);
        $this->assertTrue(AuditLog::where('action', 'LICENCE_ARCHIVED')->where('entity_id', $premises['id'])->exists());
        $this->getJson('/api/licences')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/licences?include_archived=1')->assertOk()->assertJsonCount(3, 'data');
        $this->deleteJson("/api/licences/{$premises['id']}")->assertStatus(405);
    }

    public function test_validation_and_permissions(): void
    {
        $this->postJson('/api/licences', ['holder_type' => 'BRANCH', 'licence_type' => 'NOPE', 'licence_number' => ''])
            ->assertStatus(422)->assertJsonValidationErrors(['licence_type', 'licence_number', 'expiry_date']);
        $this->postJson('/api/licences', ['holder_type' => 'EMPLOYEE', 'licence_type' => 'PPB_PHARMACIST', 'licence_number' => 'X1', 'expiry_date' => now()->addYear()->toDateString()])
            ->assertStatus(422)->assertJsonPath('error.code', 'INVALID_INPUT');
        $this->postJson('/api/licences', ['holder_type' => 'ORGANISATION', 'licence_type' => 'FIRE', 'licence_number' => 'F1', 'issue_date' => now()->toDateString(), 'expiry_date' => now()->subDay()->toDateString()])
            ->assertStatus(422)->assertJsonValidationErrors('expiry_date');
        $this->post('/api/licences', [
            'holder_type' => 'ORGANISATION', 'licence_type' => 'FIRE', 'licence_number' => 'F1', 'expiry_date' => now()->addYear()->toDateString(),
            'document' => UploadedFile::fake()->create('virus.exe', 10),
        ], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('document');

        $this->getJson('/api/licences/holders?holder_type=BRANCH')->assertOk()->assertJsonPath('0.name', 'Lodwar Main Branch');
        $this->getJson('/api/licences/holders?holder_type=SUPPLIER')->assertOk()->assertJsonPath('0.id', $this->supplier->id);

        $this->grantPermissions(['licence.view']);
        $this->getJson('/api/licences')->assertOk();
        $this->getJson('/api/licences/holders?holder_type=BRANCH')->assertStatus(403);
        $this->postJson('/api/licences', ['holder_type' => 'ORGANISATION', 'licence_type' => 'FIRE', 'licence_number' => 'F1', 'expiry_date' => now()->addYear()->toDateString()])->assertStatus(403);

        $this->grantPermissions(['stock.view']);
        $this->getJson('/api/licences')->assertStatus(403);
    }
}
