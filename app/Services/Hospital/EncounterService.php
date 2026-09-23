<?php

namespace App\Services\Hospital;

use App\Models\AuditLog;
use App\Models\Encounter;
use App\Models\EncounterCharge;
use App\Models\Facility;
use App\Models\NumberSequence;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InvalidEncounterStatusException extends \RuntimeException {}

/**
 * The encounter lifecycle. One patient, one facility, one visit:
 * REGISTERED → WAITING → IN_CONSULTATION → (AWAITING_RESULTS ⇄) → COMPLETED,
 * with CANCELLED reachable from any open state. Nothing forces a lab step,
 * a prescription, or medication — those attach only when a clinician adds
 * them.
 */
class EncounterService
{
    /**
     * Reception: register the visit and, when the facility charges one,
     * record the consultation fee as an encounter charge.
     *
     * @param array{
     *     patient_id: string, facility_id: string, department_id?: ?string,
     *     encounter_type?: string, consultation_fee?: string|numeric|null,
     *     presenting_notes?: ?string,
     * } $data
     */
    public function create(array $data, User $user): Encounter
    {
        $patient = Patient::findOrFail($data['patient_id']);
        $facility = Facility::findOrFail($data['facility_id']);
        if (! $facility->offers_hospital) {
            throw new \DomainException("Facility {$facility->name} does not offer hospital services.");
        }

        return DB::transaction(function () use ($data, $user, $patient) {
            $fee = (string) ($data['consultation_fee'] ?? '0');

            $encounter = Encounter::create([
                'encounter_no' => NumberSequence::next((string) $patient->organisation_id, 'ENCOUNTER', null, 'ENC'),
                'patient_id' => $patient->id,
                'facility_id' => $data['facility_id'],
                'department_id' => $data['department_id'] ?? null,
                'encounter_type' => $data['encounter_type'] ?? 'OUTPATIENT',
                'status' => 'WAITING',
                'consultation_fee' => $fee,
                'fee_status' => bccomp($fee, '0', 4) > 0 ? 'PENDING' : 'WAIVED',
                'presenting_notes' => $data['presenting_notes'] ?? null,
                'created_by' => $user->id,
                'started_at' => now(),
            ]);

            if (bccomp($fee, '0', 4) > 0) {
                EncounterCharge::create([
                    'encounter_id' => $encounter->id,
                    'charge_type' => 'CONSULTATION',
                    'description' => 'Consultation fee',
                    'amount' => $fee,
                    'status' => 'PENDING',
                    'recorded_by' => $user->id,
                ]);
            }

            AuditLog::record('ENCOUNTER_CREATED', 'encounter', $encounter->id, [
                'user_id' => $user->id,
                'reference' => $encounter->encounter_no,
                'after_json' => [
                    'patient_id' => $patient->id, 'patient_no' => $patient->patient_no,
                    'facility_id' => $encounter->facility_id, 'consultation_fee' => $fee,
                ],
            ]);

            return $encounter;
        });
    }

    /** The clinician calls the patient in from the queue. */
    public function startConsultation(Encounter $encounter, User $clinician): Encounter
    {
        $this->assertStatus($encounter, ['WAITING', 'REGISTERED', 'AWAITING_RESULTS']);

        $encounter->update(['status' => 'IN_CONSULTATION', 'attending_clinician_id' => $clinician->id]);

        AuditLog::record('ENCOUNTER_CONSULTATION_STARTED', 'encounter', $encounter->id, [
            'user_id' => $clinician->id,
            'reference' => $encounter->encounter_no,
        ]);

        return $encounter;
    }

    public function complete(Encounter $encounter, User $user): Encounter
    {
        $this->assertStatus($encounter, Encounter::OPEN_STATUSES);

        $encounter->update(['status' => 'COMPLETED', 'completed_at' => now()]);

        AuditLog::record('ENCOUNTER_COMPLETED', 'encounter', $encounter->id, [
            'user_id' => $user->id,
            'reference' => $encounter->encounter_no,
        ]);

        return $encounter;
    }

    public function cancel(Encounter $encounter, User $user, ?string $reason = null): Encounter
    {
        $this->assertStatus($encounter, Encounter::OPEN_STATUSES);

        $encounter->update(['status' => 'CANCELLED', 'completed_at' => now()]);

        AuditLog::record('ENCOUNTER_CANCELLED', 'encounter', $encounter->id, [
            'user_id' => $user->id,
            'reference' => $encounter->encounter_no,
            'reason' => $reason,
        ]);

        return $encounter;
    }

    /** Cash desk: settle or waive one visit-level charge. */
    public function settleCharge(EncounterCharge $charge, User $user, bool $waive = false): EncounterCharge
    {
        if ($charge->status !== 'PENDING') {
            throw new InvalidEncounterStatusException("This charge is already {$charge->status}.");
        }

        $charge->update(['status' => $waive ? 'WAIVED' : 'PAID', 'paid_at' => $waive ? null : now()]);

        if ($charge->charge_type === 'CONSULTATION') {
            $charge->encounter->update(['fee_status' => $charge->status]);
        }

        AuditLog::record($waive ? 'ENCOUNTER_CHARGE_WAIVED' : 'ENCOUNTER_CHARGE_PAID', 'encounter_charge', $charge->id, [
            'user_id' => $user->id,
            'reference' => $charge->encounter->encounter_no,
            'after_json' => ['charge_type' => $charge->charge_type, 'amount' => (string) $charge->amount],
        ]);

        return $charge;
    }

    /** @param list<string> $allowed */
    private function assertStatus(Encounter $encounter, array $allowed): void
    {
        if (! in_array($encounter->status, $allowed, true)) {
            throw new InvalidEncounterStatusException(
                "Encounter {$encounter->encounter_no} is {$encounter->status}; this action needs ".implode(' or ', $allowed).'.'
            );
        }
    }
}
