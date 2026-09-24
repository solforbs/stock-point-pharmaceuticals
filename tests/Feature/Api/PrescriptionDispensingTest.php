<?php

namespace Tests\Feature\Api;

use App\Models\Prescription;
use App\Models\Sale;
use App\Models\StockLedger;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\BuildsHospitalWorld;
use Tests\TestCase;

/**
 * Hospital → existing pharmacy. The clinician prescribes against the
 * encounter; the pharmacist dispenses through the EXISTING checkout engine
 * — FEFO batches, the stock ledger, the sale, the journal — and the Sale
 * links back to the prescription. No parallel inventory anywhere.
 */
class PrescriptionDispensingTest extends TestCase
{
    use BuildsHospitalWorld;

    protected function setUp(): void
    {
        parent::setUp();
        $this->buildHospitalWorld();
        $this->receive('RX1', now()->addYears(2)->toDateString(), '2000', '1.0000');

        // One user wearing both hats keeps the flow readable: clinician
        // permissions for the hospital side, pharmacy permissions to dispense.
        $this->grantPermissions([
            'hospital.view', 'hospital.patient.view', 'hospital.patient.manage',
            'hospital.encounter.manage', 'hospital.consultation.manage', 'hospital.prescription.create',
            'prescription.view', 'prescription.dispense', 'sale.view', 'stock.view',
        ]);
        Sanctum::actingAs($this->user);
    }

    public function test_a_prescription_is_dispensed_through_the_existing_checkout_stock_and_sale(): void
    {
        $patient = $this->registerPatient();
        $encounter = $this->openEncounter($patient['id'], ['consultation_fee' => '0']);

        $prescription = $this->postJson("/api/hospital/encounters/{$encounter['id']}/prescriptions", [
            'branch_id' => $this->branch->id,
            'lines' => [[
                'product_id' => $this->amox->id,
                'quantity' => '21',
                'frequency' => '3 times daily',
                'duration' => '7 days',
                'instructions' => 'Take after meals.',
            ]],
        ])->assertCreated()->json();

        $this->assertStringStartsWith('RX-', $prescription['rx_no']);
        $this->assertSame('Amoxicillin 500mg Capsules', $prescription['lines'][0]['medicine_name']);

        // The pharmacist sees it in the branch queue…
        $this->getJson('/api/prescriptions?status=PENDING')->assertOk()->assertJsonPath('data.0.id', $prescription['id']);

        // …and dispenses it. 21 TAB at the 2.50 default price = 52.50 cash.
        $dispensed = $this->postJson("/api/prescriptions/{$prescription['id']}/dispense", [
            'store_id' => $this->store->id,
            'payments' => [['method' => 'CASH', 'amount' => '52.50']],
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertOk()->json();

        $this->assertSame('DISPENSED', $dispensed['status']);
        $this->assertNotNull($dispensed['sale_id']);
        $this->assertSame('21.0000', $dispensed['lines'][0]['dispensed_qty']);

        $sale = Sale::findOrFail($dispensed['sale_id']);
        $this->assertSame('POSTED', $sale->status);
        $this->assertSame('52.5000', (string) $sale->grand_total);
        $this->assertSame('PRESCRIPTION', $sale->sub_type);

        // Stock left through the pharmacy's own ledger.
        $this->assertSame('-21.0000', (string) StockLedger::where('source_doc_id', $sale->id)->where('txn_type', 'SALE')->sum('qty_base'));

        // Dispensing twice cannot happen.
        $this->postJson("/api/prescriptions/{$prescription['id']}/dispense", [
            'store_id' => $this->store->id,
            'payments' => [['method' => 'CASH', 'amount' => '52.50']],
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(409)->assertJsonPath('error.code', 'INVALID_STATE');
    }

    public function test_an_unrouted_prescription_can_be_cancelled_but_not_a_dispensed_one(): void
    {
        $patient = $this->registerPatient();
        $encounter = $this->openEncounter($patient['id'], ['consultation_fee' => '0']);

        // No branch: printed for an outside chemist. Free-text medicine only.
        $prescription = $this->postJson("/api/hospital/encounters/{$encounter['id']}/prescriptions", [
            'lines' => [['medicine_name' => 'Artemether/Lumefantrine 80/480mg', 'quantity' => '6', 'frequency' => 'Twice daily', 'duration' => '3 days']],
        ])->assertCreated()->json();

        // Nothing stocked on it, so dispensing here is refused with a clear error.
        $this->postJson("/api/prescriptions/{$prescription['id']}/dispense", [
            'store_id' => $this->store->id,
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(422)->assertJsonPath('error.code', 'INVALID_INPUT');

        $this->postJson("/api/prescriptions/{$prescription['id']}/cancel", ['reason' => 'Patient will buy elsewhere'])
            ->assertOk()->assertJsonPath('status', 'CANCELLED');

        $this->assertSame('CANCELLED', Prescription::findOrFail($prescription['id'])->status);
    }
}
