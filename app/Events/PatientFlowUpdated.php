<?php

namespace App\Events;

use App\Models\Encounter;
use App\Models\LabOrder;
use App\Models\Prescription;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A patient moved a step: the encounter changed stage, a lab order advanced
 * through the bench, or a prescription was written or dispensed. Every
 * screen watching a queue (reception, the clinician's list, the lab bench,
 * the pharmacy) refreshes the moment it happens instead of on the next poll.
 *
 * The payload carries identifiers and the new stage only — clinical content
 * stays behind the API and its permission checks. Dispatch waits for the
 * database commit, so a listener that refetches always sees the new state.
 */
class PatientFlowUpdated implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $organisationId,
        public readonly string $kind, // ENCOUNTER | LAB_ORDER | PRESCRIPTION
        public readonly array $payload,
    ) {}

    public static function forEncounter(Encounter $encounter): self
    {
        return new self((string) $encounter->organisation_id, 'ENCOUNTER', [
            'id' => (string) $encounter->id,
            'number' => $encounter->encounter_no,
            'status' => $encounter->status,
            'patient_name' => $encounter->patient?->fullName(),
            'facility_id' => (string) $encounter->facility_id,
        ]);
    }

    public static function forLabOrder(LabOrder $order): self
    {
        return new self((string) $order->organisation_id, 'LAB_ORDER', [
            'id' => (string) $order->id,
            'number' => $order->order_no,
            'status' => $order->status,
            'encounter_id' => (string) $order->encounter_id,
            'facility_id' => (string) $order->facility_id,
        ]);
    }

    public static function forPrescription(Prescription $prescription): self
    {
        return new self((string) $prescription->organisation_id, 'PRESCRIPTION', [
            'id' => (string) $prescription->id,
            'number' => $prescription->rx_no,
            'status' => $prescription->status,
            'encounter_id' => (string) $prescription->encounter_id,
            'branch_id' => $prescription->branch_id ? (string) $prescription->branch_id : null,
        ]);
    }

    /**
     * @return list<Channel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('healthcare.'.$this->organisationId)];
    }

    public function broadcastAs(): string
    {
        return 'patient-flow.updated';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['kind' => $this->kind] + $this->payload;
    }
}
