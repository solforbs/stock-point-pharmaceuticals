<?php

namespace App\Services\Hospital;

use App\Events\PatientFlowUpdated;
use App\Jobs\RescanBranchAlerts;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Encounter;
use App\Models\NumberSequence;
use App\Models\Prescription;
use App\Models\PrescriptionLine;
use App\Models\Product;
use App\Models\ProductUom;
use App\Models\User;
use App\Services\Pricing\PriceQuoteService;
use App\Services\Sales\CheckoutService;
use App\Services\Sales\SaleModes;
use Illuminate\Support\Facades\DB;

class InvalidPrescriptionStatusException extends \RuntimeException {}

/**
 * Prescriptions: written by a clinician against an encounter, routed to an
 * existing pharmacy branch, and dispensed through the EXISTING checkout
 * engine — pricing, FEFO batch allocation, stock deduction, the sale, the
 * journal and the receipt are all the pharmacy's own machinery. This module
 * adds no second inventory.
 */
class PrescriptionService
{
    public function __construct(
        private readonly PriceQuoteService $quotes,
        private readonly CheckoutService $checkout,
    ) {}

    /**
     * @param list<array{
     *     product_id?: ?string, medicine_name?: ?string, quantity: string|numeric,
     *     uom_id?: ?string, frequency?: ?string, duration?: ?string, instructions?: ?string,
     * }> $lines
     */
    public function create(Encounter $encounter, array $lines, User $prescriber, ?string $branchId, ?string $notes = null): Prescription
    {
        if ($lines === []) {
            throw new \DomainException('A prescription needs at least one medicine.');
        }

        return DB::transaction(function () use ($encounter, $lines, $prescriber, $branchId, $notes) {
            $prescription = Prescription::create([
                'rx_no' => NumberSequence::next((string) $encounter->organisation_id, 'PRESCRIPTION', null, 'RX'),
                'encounter_id' => $encounter->id,
                'patient_id' => $encounter->patient_id,
                'branch_id' => $branchId,
                'prescribed_by' => $prescriber->id,
                'status' => 'PENDING',
                'notes' => $notes,
            ]);

            foreach ($lines as $line) {
                $product = ! empty($line['product_id']) ? Product::findOrFail($line['product_id']) : null;
                $uomId = $line['uom_id'] ?? null;
                // A prescribed quantity ("21, three times daily") is counted in
                // dispensing units — the base UOM (tablet, capsule), never the
                // wholesale default pack.
                if ($product && ! $uomId) {
                    $uomId = ProductUom::where('product_id', $product->id)
                        ->orderByDesc('is_base')->orderByDesc('is_default_sales')
                        ->value('uom_id');
                }

                $name = $product?->name ?? trim((string) ($line['medicine_name'] ?? ''));
                if ($name === '') {
                    throw new \DomainException('Each prescription line needs a stocked product or a medicine name.');
                }

                PrescriptionLine::create([
                    'prescription_id' => $prescription->id,
                    'product_id' => $product?->id,
                    'uom_id' => $product ? $uomId : null,
                    'medicine_name' => $name,
                    'quantity' => (string) $line['quantity'],
                    'frequency' => $line['frequency'] ?? null,
                    'duration' => $line['duration'] ?? null,
                    'instructions' => $line['instructions'] ?? null,
                ]);
            }

            event(PatientFlowUpdated::forPrescription($prescription));
            $this->rescanAlerts($prescription);

            AuditLog::record('PRESCRIPTION_CREATED', 'prescription', $prescription->id, [
                'user_id' => $prescriber->id,
                'reference' => $prescription->rx_no,
                'after_json' => [
                    'encounter_no' => $encounter->encounter_no,
                    'branch_id' => $branchId,
                    'lines' => collect($lines)->map(fn ($l) => ($l['medicine_name'] ?? $l['product_id']).' x'.$l['quantity'])->all(),
                ],
            ]);

            return $prescription->fresh(['lines']);
        });
    }

    /**
     * Pharmacist-side: price the stocked lines through the pricing engine and
     * post them through the existing checkout — FEFO allocation, stock
     * deduction, sale, journal, eTIMS — then link the Sale back. Free-text
     * lines (nothing stocked) are left for an outside pharmacy.
     *
     * @param  array{store_id: string, idempotency_key: string, payments?: list<array{method: string, reference?: ?string, amount: string}>}  $meta
     */
    public function dispense(Prescription $prescription, User $pharmacist, string $branchId, array $meta): Prescription
    {
        if ($prescription->status !== 'PENDING') {
            throw new InvalidPrescriptionStatusException("Prescription {$prescription->rx_no} is {$prescription->status}; only a PENDING prescription can be dispensed.");
        }
        if ($prescription->branch_id !== null && $prescription->branch_id !== $branchId) {
            throw new \DomainException('This prescription was sent to a different pharmacy branch.');
        }

        $stocked = $prescription->lines->filter(fn (PrescriptionLine $l) => $l->product_id !== null)->values();
        if ($stocked->isEmpty()) {
            throw new \DomainException('None of the lines on this prescription reference a stocked product; there is nothing to dispense here.');
        }

        $branch = Branch::findOrFail($branchId);
        $saleMode = in_array('DISPENSING', SaleModes::enabledFor($branch), true) ? 'DISPENSING' : SaleModes::defaultFor($branch);

        $quote = $this->quotes->quote([
            'organisation_id' => (string) $prescription->organisation_id,
            'branch_id' => $branchId,
            'store_id' => $meta['store_id'],
            'sale_mode' => $saleMode,
            'user_id' => $pharmacist->id,
            'lines' => $stocked->map(fn (PrescriptionLine $l) => [
                'product_id' => $l->product_id,
                'uom_id' => $l->uom_id,
                'quantity' => (string) $l->quantity,
            ])->all(),
        ], persist: false);

        $sale = $this->checkout->checkout([
            'organisation_id' => (string) $prescription->organisation_id,
            'branch_id' => $branchId,
            'store_id' => $meta['store_id'],
            'sale_mode' => $saleMode,
            'sub_type' => 'PRESCRIPTION',
            'user_id' => $pharmacist->id,
            'idempotency_key' => $meta['idempotency_key'],
            'lines' => collect($quote['lines'])->map(fn (array $line) => [
                'product_id' => $line['product_id'],
                'uom_id' => $line['uom_id'],
                'qty' => $line['quantity'],
                'list_price' => $line['break_price'],
                'unit_price' => $line['unit_price'],
                'discount_amount' => $line['discount_amount'],
                'discount_pct' => $line['discount_pct'],
                'discount_source' => $line['discount_source'] === 'NONE' ? null : $line['discount_source'],
                'tax_code_id' => $line['tax_code_id'],
                'tax_rate' => $line['tax_rate'],
                'tax_amount' => $line['tax_amount'],
                'min_shelf_life_days' => $quote['min_shelf_life_days'],
            ])->all(),
            'payments' => $meta['payments'] ?? [],
        ]);

        DB::transaction(function () use ($prescription, $pharmacist, $stocked, $sale) {
            foreach ($stocked as $line) {
                $line->update(['dispensed_qty' => $line->quantity]);
            }

            $prescription->update([
                'status' => 'DISPENSED',
                'sale_id' => $sale->id,
                'dispensed_by' => $pharmacist->id,
                'dispensed_at' => now(),
            ]);

            event(PatientFlowUpdated::forPrescription($prescription));
            $this->rescanAlerts($prescription);

            AuditLog::record('PRESCRIPTION_DISPENSED', 'prescription', $prescription->id, [
                'user_id' => $pharmacist->id,
                'reference' => $prescription->rx_no,
                'after_json' => ['sale_id' => $sale->id, 'sale_number' => $sale->doc_number, 'grand_total' => (string) $sale->grand_total],
            ]);
        });

        return $prescription->fresh(['lines', 'sale']);
    }

    public function cancel(Prescription $prescription, User $user, ?string $reason = null): Prescription
    {
        if ($prescription->status !== 'PENDING') {
            throw new InvalidPrescriptionStatusException("Prescription {$prescription->rx_no} is {$prescription->status}; only a PENDING prescription can be cancelled.");
        }

        $prescription->update(['status' => 'CANCELLED', 'cancelled_by' => $user->id, 'cancelled_at' => now()]);
        event(PatientFlowUpdated::forPrescription($prescription));
        $this->rescanAlerts($prescription);

        AuditLog::record('PRESCRIPTION_CANCELLED', 'prescription', $prescription->id, [
            'user_id' => $user->id,
            'reference' => $prescription->rx_no,
            'reason' => $reason,
        ]);

        return $prescription;
    }

    /**
     * What the pharmacy will charge, computed through the same pricing
     * engine dispensing uses — so the number the clinician quotes the
     * patient is the number at the till, not an estimate that drifts.
     * Cost, profit and margin never leave this method: the caller may be
     * a clinician, and those figures belong to the pharmacy module.
     *
     * @param  list<array{product_id: string, uom_id?: ?string, quantity: string}>  $lines  stocked lines only
     * @return ?array{lines: list<array<string, string>>, subtotal: string, discount: string, tax: string, grand_total: string}
     */
    public function priceEstimate(string $organisationId, string $branchId, int $userId, array $lines): ?array
    {
        $stocked = collect($lines)->filter(fn (array $l) => ! empty($l['product_id']))->values();
        if ($stocked->isEmpty()) {
            return null;
        }

        $storeId = DB::table('stores')->where('branch_id', $branchId)->where('is_sellable', true)->orderBy('code')->value('id')
            ?? DB::table('stores')->where('branch_id', $branchId)->orderBy('code')->value('id');
        if (! $storeId) {
            return null;
        }

        $branch = Branch::findOrFail($branchId);
        $saleMode = in_array('DISPENSING', SaleModes::enabledFor($branch), true) ? 'DISPENSING' : SaleModes::defaultFor($branch);

        $quote = $this->quotes->quote([
            'organisation_id' => $organisationId,
            'branch_id' => $branchId,
            'store_id' => (string) $storeId,
            'sale_mode' => $saleMode,
            'user_id' => $userId,
            'lines' => $stocked->map(fn (array $l) => [
                'product_id' => $l['product_id'],
                // Prescribed quantities are in dispensing units (the base
                // UOM), exactly as create() records them.
                'uom_id' => $l['uom_id'] ?? ProductUom::where('product_id', $l['product_id'])
                    ->orderByDesc('is_base')->orderByDesc('is_default_sales')->value('uom_id'),
                'quantity' => (string) $l['quantity'],
            ])->all(),
        ], persist: false);

        return [
            'lines' => collect($quote['lines'])->map(fn (array $line) => [
                'product_id' => $line['product_id'],
                'product_name' => $line['product_name'],
                'uom_code' => $line['uom_code'],
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
                'tax_amount' => $line['tax_amount'],
                'line_total' => $line['line_total'],
            ])->all(),
            'subtotal' => $quote['totals']['subtotal'],
            'discount' => $quote['totals']['discount'],
            'tax' => $quote['totals']['tax'],
            'grand_total' => $quote['totals']['grand_total'],
        ];
    }

    /**
     * The bell should not wait for tomorrow's 05:30 scan: a prescription
     * arriving at (or leaving) the pharmacy queue rescans its branch's
     * alerts once the surrounding transaction commits. External
     * prescriptions have no branch and raise no alert.
     */
    private function rescanAlerts(Prescription $prescription): void
    {
        if ($prescription->branch_id) {
            RescanBranchAlerts::dispatch((string) $prescription->branch_id)->afterCommit();
        }
    }
}
