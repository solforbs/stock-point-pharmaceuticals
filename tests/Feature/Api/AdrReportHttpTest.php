<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\Product;
use App\Models\ProductBatch;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsBlueprintWorld;
use Tests\TestCase;

/**
 * Part 11.4 — adverse drug reactions: DRAFT → SUBMITTED → CLOSED, linked to a
 * batch so a pattern can be seen.
 */
class AdrReportHttpTest extends TestCase
{
    use BuildsBlueprintWorld;

    private ProductBatch $batch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildWorld();
        $this->batch = $this->receive('A1', now()->addYear()->toDateString(), '100', '2.0000');
        $this->grantPermissions(['adr.report', 'adr.manage']);
        Sanctum::actingAs($this->user);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'product_id' => $this->amox->id,
            'batch_id' => $this->batch->id,
            'customer_id' => $this->customer->id,
            'patient_initials' => 'J.K.',
            'patient_age' => 34,
            'patient_sex' => 'F',
            'reaction_description' => 'Generalised urticarial rash two hours after the first dose.',
            'onset_date' => now()->subDays(2)->toDateString(),
            'seriousness' => 'NON_SERIOUS',
            'outcome' => 'RECOVERING',
            'action_taken' => 'Drug withdrawn; antihistamine given.',
            'reporter_name' => 'Dr. A. Pharmacist',
        ], $overrides);
    }

    public function test_a_report_is_drafted_edited_submitted_referenced_and_closed(): void
    {
        $report = $this->postJson('/api/adr-reports', $this->payload())->assertCreated()
            ->assertJsonPath('status', 'DRAFT')
            ->assertJsonPath('batch.batch_number', 'A1')
            ->assertJsonPath('product.code', 'AMOX500')
            ->json();
        $this->assertStringStartsWith('ADR-', $report['doc_number']);

        $this->patchJson("/api/adr-reports/{$report['id']}", ['outcome' => 'RECOVERED'])->assertOk()->assertJsonPath('outcome', 'RECOVERED');
        $this->getJson('/api/adr-reports?status=DRAFT&product_id='.$this->amox->id)->assertOk()->assertJsonPath('total', 1);
        $this->getJson('/api/adr-reports?seriousness=FATAL')->assertOk()->assertJsonPath('total', 0);

        $this->postJson("/api/adr-reports/{$report['id']}/submit")->assertOk()->assertJsonPath('status', 'SUBMITTED')->assertJsonPath('submitter.id', $this->user->id);
        $this->assertFalse(AuditLog::where('action', 'ADR_SERIOUS_REPORTED')->exists(), 'a non-serious report does not escalate');

        // Once submitted only the PPB reference and investigation notes change.
        $this->patchJson("/api/adr-reports/{$report['id']}", ['outcome' => 'FATAL'])->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATE');
        $this->patchJson("/api/adr-reports/{$report['id']}", ['ppb_reference' => 'PViMS-2026-00417'])->assertOk()->assertJsonPath('ppb_reference', 'PViMS-2026-00417');
        $this->postJson("/api/adr-reports/{$report['id']}/submit")->assertStatus(409);

        $this->postJson("/api/adr-reports/{$report['id']}/close", ['investigation_notes' => 'Known hypersensitivity; no quality defect.'])
            ->assertOk()->assertJsonPath('status', 'CLOSED')->assertJsonPath('closer.id', $this->user->id);
        $this->patchJson("/api/adr-reports/{$report['id']}", ['ppb_reference' => 'X'])->assertStatus(409);
    }

    public function test_serious_reports_flag_the_director_and_need_a_ppb_reference_to_close(): void
    {
        $report = $this->postJson('/api/adr-reports', $this->payload(['seriousness' => 'LIFE_THREATENING', 'outcome' => 'NOT_RECOVERED']))->assertCreated()->json();
        $this->postJson("/api/adr-reports/{$report['id']}/submit")->assertOk();

        $flag = AuditLog::where('action', 'ADR_SERIOUS_REPORTED')->where('entity_id', $report['id'])->firstOrFail();
        $this->assertSame('DIRECTOR', $flag->getAttribute('after_json')['attention']);

        $this->postJson("/api/adr-reports/{$report['id']}/close")->assertStatus(422)->assertJsonPath('error.code', 'PPB_REFERENCE_REQUIRED');
        $this->postJson("/api/adr-reports/{$report['id']}/close", ['ppb_reference' => 'PViMS-2026-00418'])->assertOk()->assertJsonPath('status', 'CLOSED');
    }

    public function test_three_reports_against_one_batch_raise_a_signal(): void
    {
        foreach (range(1, 3) as $i) {
            $id = $this->postJson('/api/adr-reports', $this->payload(['patient_initials' => "P{$i}"]))->assertCreated()->json('id');
            $this->postJson("/api/adr-reports/{$id}/submit")->assertOk();
        }

        $this->assertSame(1, AuditLog::where('action', 'ADR_BATCH_SIGNAL')->where('entity_id', $this->batch->id)->count());
    }

    public function test_validation_and_permissions(): void
    {
        $this->postJson('/api/adr-reports', $this->payload(['seriousness' => 'BAD', 'onset_date' => now()->addDay()->toDateString(), 'reaction_description' => '']))
            ->assertStatus(422)->assertJsonValidationErrors(['seriousness', 'onset_date', 'reaction_description']);

        $other = $this->receive('A2', now()->addYear()->toDateString(), '10', '2.0000');
        $other->update(['product_id' => Product::create([
            'organisation_id' => $this->org->id, 'code' => 'PARA', 'name' => 'Paracetamol', 'base_uom_id' => $this->uoms['TAB']->id,
        ])->id]);
        $this->postJson('/api/adr-reports', $this->payload(['batch_id' => $other->id]))->assertStatus(422)->assertJsonPath('error.code', 'INVALID_INPUT');

        $report = $this->postJson('/api/adr-reports', $this->payload())->assertCreated()->json();

        $this->grantPermissions(['adr.report']);
        $this->getJson('/api/adr-reports')->assertOk();
        $this->postJson("/api/adr-reports/{$report['id']}/submit")->assertOk();
        $this->postJson("/api/adr-reports/{$report['id']}/close")->assertStatus(403);
        $this->patchJson("/api/adr-reports/{$report['id']}", ['ppb_reference' => 'X'])->assertStatus(403);

        $this->grantPermissions(['stock.view']);
        $this->getJson('/api/adr-reports')->assertStatus(403);
        $this->postJson('/api/adr-reports', $this->payload())->assertStatus(403);
    }
}
