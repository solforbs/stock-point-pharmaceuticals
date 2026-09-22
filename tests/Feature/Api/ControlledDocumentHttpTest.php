<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * V6 Part 16.4 — SOPs: versioned, acknowledged per version, and every
 * signed-in user can read and acknowledge what is ACTIVE.
 */
class ControlledDocumentHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        Storage::fake('local');
        $this->grantPermissions(['document.manage']);
        Sanctum::actingAs($this->user);
    }

    /**
     * @return array<string, mixed>
     */
    private function sop(array $overrides = []): array
    {
        return array_merge([
            'code' => 'sop-cc-01', 'title' => 'Cold chain monitoring', 'category' => 'SOP',
            'review_due_date' => now()->addYear()->toDateString(),
            'version' => '1.0', 'change_summary' => 'First issue', 'effective_date' => now()->toDateString(),
            'file' => UploadedFile::fake()->create('sop-cc-01.pdf', 200, 'application/pdf'),
        ], $overrides);
    }

    public function test_a_document_is_issued_activated_acknowledged_and_revised(): void
    {
        $doc = $this->post('/api/documents', $this->sop(), ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('code', 'SOP-CC-01')
            ->assertJsonPath('status', 'DRAFT')
            ->assertJsonPath('current_version.version', '1.0')
            ->json();
        $id = $doc['id'];

        $this->postJson("/api/documents/{$id}/acknowledge")->assertStatus(409);
        $this->postJson("/api/documents/{$id}/activate")->assertOk()->assertJsonPath('status', 'ACTIVE');

        // Any signed-in user, without document.manage, can read and acknowledge.
        $cashier = User::create(['name' => 'Plain Cashier', 'username' => 'plain', 'email' => 'plain@example.test', 'password' => 'password-long-enough']);
        $this->grantPermissionsTo($cashier);
        Sanctum::actingAs($cashier);

        $this->getJson('/api/documents')->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.my_acknowledged_at', null);
        $this->get("/api/documents/{$id}/versions/{$doc['current_version']['id']}/download")->assertOk()->assertDownload('sop-cc-01.pdf');
        $this->postJson("/api/documents/{$id}/acknowledge")->assertCreated();
        $this->postJson("/api/documents/{$id}/acknowledge")->assertOk();
        $this->getJson('/api/documents')->assertOk()->assertJsonPath('data.0.acknowledgement_count', 1);
        $this->assertNotNull($this->getJson('/api/documents')->json('data.0.my_acknowledged_at'));
        $this->assertSame(1, AuditLog::where('action', 'DOCUMENT_ACKNOWLEDGED')->where('entity_id', $id)->count());
        $this->postJson("/api/documents/{$id}/retire")->assertStatus(403);
        $this->getJson("/api/documents/{$id}/acknowledgements")->assertStatus(403);

        Sanctum::actingAs($this->user);
        $this->getJson("/api/documents/{$id}/acknowledgements")->assertOk()
            ->assertJsonCount(1, 'acknowledged')
            ->assertJsonPath('acknowledged.0.user.name', 'Plain Cashier');

        // Version 1.1 becomes current; acknowledgements start from zero.
        $this->post("/api/documents/{$id}/versions", [
            'version' => '1.1', 'change_summary' => 'Logger download added', 'effective_date' => now()->addWeek()->toDateString(),
            'file' => UploadedFile::fake()->create('sop-cc-01-v1.1.pdf', 200, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('version', '1.1');
        $this->post("/api/documents/{$id}/versions", [
            'version' => '1.1', 'effective_date' => now()->toDateString(), 'file' => UploadedFile::fake()->create('dup.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->getJson("/api/documents/{$id}")->assertOk()->assertJsonCount(2, 'versions')->assertJsonPath('current_version.version', '1.1');
        $this->getJson("/api/documents/{$id}/acknowledgements")->assertOk()->assertJsonCount(0, 'acknowledged');

        Sanctum::actingAs($cashier);
        $this->getJson('/api/documents')->assertOk()->assertJsonPath('data.0.my_acknowledged_at', null)->assertJsonPath('data.0.acknowledgement_count', 0);

        Sanctum::actingAs($this->user);
        $this->postJson("/api/documents/{$id}/retire")->assertOk()->assertJsonPath('status', 'RETIRED');
        $this->postJson("/api/documents/{$id}/retire")->assertStatus(409);

        Sanctum::actingAs($cashier);
        $this->getJson('/api/documents')->assertOk()->assertJsonPath('total', 0);
        $this->getJson("/api/documents/{$id}")->assertNotFound();
    }

    public function test_an_sop_is_started_from_a_template_edited_and_revised_in_the_app(): void
    {
        $templates = $this->getJson('/api/documents/templates')->assertOk()->json('data');
        $receiving = collect($templates)->firstWhere('key', 'receiving');
        $this->assertStringContainsString($this->org->name, $receiving['sections']['purpose'], 'the template carries the institution name');

        $sections = $receiving['sections'];
        $sections['procedure'] .= "\nCount cartons in front of the driver before signing.";

        $doc = $this->postJson('/api/documents/from-template', [
            'template_key' => 'receiving', 'code' => 'sop-001', 'title' => $receiving['title'],
            'effective_date' => now()->toDateString(), 'sections' => $sections,
        ])->assertCreated()
            ->assertJsonPath('code', 'SOP-001')
            ->assertJsonPath('status', 'DRAFT')
            ->assertJsonPath('current_version.version', '1.0')
            ->json();

        $version = DocumentVersion::findOrFail($doc['current_version']['id']);
        $this->assertStringContainsString('Count cartons in front of the driver', $version->content_json['procedure']);
        Storage::disk('local')->assertExists($version->file_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($version->file_path));

        $sections['scope'] = 'All deliveries to every branch.';
        $this->postJson("/api/documents/{$doc['id']}/versions/from-text", [
            'version' => '1.1', 'change_summary' => 'Scope widened', 'effective_date' => now()->toDateString(), 'sections' => $sections,
        ])->assertCreated()->assertJsonPath('version', '1.1')->assertJsonPath('content_json.scope', 'All deliveries to every branch.');

        $this->postJson('/api/documents/from-template', [
            'template_key' => 'nope', 'code' => 'sop-x', 'title' => 'X', 'effective_date' => now()->toDateString(), 'sections' => $sections,
        ])->assertStatus(422);
    }

    public function test_validation_and_permissions(): void
    {
        $this->post('/api/documents', $this->sop(['file' => null, 'category' => 'MEMO']), ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors(['file', 'category']);
        $this->post('/api/documents', $this->sop(), ['Accept' => 'application/json'])->assertCreated();
        $this->post('/api/documents', $this->sop(['code' => 'SOP-CC-01']), ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors('code');

        $this->grantPermissions(['stock.view']);
        $this->post('/api/documents', $this->sop(['code' => 'SOP-2']), ['Accept' => 'application/json'])->assertStatus(403);
        $this->getJson('/api/documents')->assertOk()->assertJsonPath('total', 0);
    }

    private function grantPermissionsTo(User $user): void
    {
        $original = $this->user;
        $this->user = $user;
        $this->grantPermissions(['sale.view'], 'Cashier role');
        $this->user = $original;
    }
}
