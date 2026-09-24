<?php

namespace Tests\Feature\Api;

use App\Models\Alert;
use App\Models\Prescription;
use App\Models\Sale;
use App\Models\StockLedger;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
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

    /**
     * The prescription form's medicine search. A clinician holds no
     * product.view (that permission would open the whole pharmacy module),
     * so /api/products refuses them — the hospital-side catalogue read is
     * what the form searches, by name or code.
     */
    public function test_a_clinician_searches_medicines_without_the_pharmacy_permission(): void
    {
        $clinician = User::create([
            'organisation_id' => $this->org->id,
            'name' => 'Dr Achieng', 'username' => 'achieng', 'email' => 'achieng@stockpoint.test',
            'password' => Hash::make('a-long-enough-password'), 'is_active' => true,
        ]);
        $role = $this->grantPermissions(['hospital.view', 'hospital.prescription.create'], 'Clinician only');
        app(PermissionRegistrar::class)->setPermissionsTeamId($this->branch->id);
        $clinician->assignRole($role);
        app(PermissionRegistrar::class)->setPermissionsTeamId(null);
        Sanctum::actingAs($clinician);

        $this->getJson('/api/products?q=AMOX500')->assertForbidden();

        $this->getJson('/api/hospital/medicines?q=AMOX500')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'AMOX500')
            ->assertJsonPath('data.0.name', 'Amoxicillin 500mg Capsules');
    }

    /**
     * Part 17 meets the pharmacy queue: a prescription waiting at the
     * branch is a standing alert the moment it is written (the service
     * rescans the branch rather than waiting for the 05:30 schedule), and
     * dispensing it resolves the alert on the same rescan.
     */
    public function test_a_waiting_prescription_raises_an_alert_that_dispensing_resolves(): void
    {
        $patient = $this->registerPatient();
        $encounter = $this->openEncounter($patient['id'], ['consultation_fee' => '0']);

        $prescription = $this->postJson("/api/hospital/encounters/{$encounter['id']}/prescriptions", [
            'branch_id' => $this->branch->id,
            'lines' => [['product_id' => $this->amox->id, 'quantity' => '21', 'frequency' => '3 times daily', 'duration' => '7 days']],
        ])->assertCreated()->json();

        $alert = Alert::where('type', 'PRESCRIPTION_PENDING')->where('entity_id', $prescription['id'])->firstOrFail();
        $this->assertSame('INFO', $alert->severity);
        $this->assertSame('PRESCRIPTION', $alert->category);
        $this->assertNull($alert->resolved_at);
        $this->assertStringContainsString($prescription['rx_no'], $alert->title);

        $this->postJson("/api/prescriptions/{$prescription['id']}/dispense", [
            'store_id' => $this->store->id,
            'payments' => [['method' => 'CASH', 'amount' => '52.50']],
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertOk();

        $this->assertNotNull($alert->fresh()->resolved_at, 'dispensing resolves the waiting-prescription alert');
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

        // …the clinician could already quote the till's number while
        // writing it, and opening it shows the same priced total…
        $this->postJson('/api/hospital/prescription-estimate', [
            'branch_id' => $this->branch->id,
            'lines' => [['product_id' => $this->amox->id, 'quantity' => '21']],
        ])->assertOk()->assertJsonPath('grand_total', '52.5000');
        $this->getJson("/api/prescriptions/{$prescription['id']}")
            ->assertOk()->assertJsonPath('estimate.grand_total', '52.5000');

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
