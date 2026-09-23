<?php

namespace App\Services\Laboratory;

use App\Models\AuditLog;
use App\Models\Encounter;
use App\Models\EncounterCharge;
use App\Models\Facility;
use App\Models\LabOrder;
use App\Models\LabOrderTest;
use App\Models\LabSample;
use App\Models\LabTest;
use App\Models\NumberSequence;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InvalidLabOrderStatusException extends \RuntimeException {}

/**
 * The lab order state machine. Statuses only ever move along
 * PENDING → ACCEPTED → SAMPLE_COLLECTED → PROCESSING → COMPLETED, with
 * CANCELLED allowed from any state before COMPLETED; nothing writes
 * `status` outside this service, and every step records its actor, its
 * time and an audit row.
 */
class LabOrderService
{
    private const TRANSITIONS = [
        'accept' => ['from' => ['PENDING'], 'to' => 'ACCEPTED'],
        'collectSample' => ['from' => ['ACCEPTED'], 'to' => 'SAMPLE_COLLECTED'],
        'startProcessing' => ['from' => ['SAMPLE_COLLECTED'], 'to' => 'PROCESSING'],
        'complete' => ['from' => ['PROCESSING'], 'to' => 'COMPLETED'],
        'cancel' => ['from' => ['PENDING', 'ACCEPTED', 'SAMPLE_COLLECTED', 'PROCESSING'], 'to' => 'CANCELLED'],
    ];

    /**
     * Clinician-side: order tests for an encounter, snapshotting each test's
     * name, price and normal range, billing them to the visit, and parking
     * the encounter on AWAITING_RESULTS.
     *
     * @param  list<string>  $testIds
     */
    public function create(Encounter $encounter, string $facilityId, array $testIds, User $orderedBy, ?string $clinicalNotes = null): LabOrder
    {
        $facility = Facility::findOrFail($facilityId);
        if (! $facility->offers_laboratory) {
            throw new \DomainException("Facility {$facility->name} does not offer laboratory services.");
        }

        $tests = LabTest::whereIn('id', $testIds)->where('is_active', true)->get();
        if ($tests->isEmpty() || $tests->count() !== count(array_unique($testIds))) {
            throw new \DomainException('One or more of the requested tests do not exist or are inactive.');
        }

        return DB::transaction(function () use ($encounter, $facility, $tests, $orderedBy, $clinicalNotes) {
            $order = LabOrder::create([
                'order_no' => NumberSequence::next((string) $encounter->organisation_id, 'LAB_ORDER', null, 'LAB'),
                'encounter_id' => $encounter->id,
                'patient_id' => $encounter->patient_id,
                'facility_id' => $facility->id,
                'ordered_by' => $orderedBy->id,
                'clinical_notes' => $clinicalNotes,
                'status' => 'PENDING',
            ]);

            foreach ($tests as $test) {
                LabOrderTest::create([
                    'lab_order_id' => $order->id,
                    'lab_test_id' => $test->id,
                    'test_name' => $test->name,
                    'price' => $test->price,
                    'normal_range' => $test->normal_range,
                    'unit' => $test->unit,
                ]);

                EncounterCharge::create([
                    'encounter_id' => $encounter->id,
                    'charge_type' => 'LAB_TEST',
                    'description' => $test->name,
                    'amount' => $test->price,
                    'status' => 'PENDING',
                    'source_type' => 'lab_order',
                    'source_id' => $order->id,
                    'recorded_by' => $orderedBy->id,
                ]);
            }

            if ($encounter->status === 'IN_CONSULTATION') {
                $encounter->update(['status' => 'AWAITING_RESULTS']);
            }

            AuditLog::record('LAB_ORDER_CREATED', 'lab_order', $order->id, [
                'user_id' => $orderedBy->id,
                'reference' => $order->order_no,
                'after_json' => [
                    'encounter_no' => $encounter->encounter_no,
                    'facility_id' => $facility->id,
                    'tests' => $tests->pluck('name')->all(),
                ],
            ]);

            return $order;
        });
    }

    public function accept(LabOrder $order, User $user): LabOrder
    {
        $this->transition($order, 'accept', $user);
        $order->update(['accepted_by' => $user->id, 'accepted_at' => now()]);

        return $order;
    }

    /**
     * @param  array{sample_type: string, condition_notes?: ?string}  $data
     */
    public function collectSample(LabOrder $order, array $data, User $user): LabSample
    {
        return DB::transaction(function () use ($order, $data, $user) {
            $this->transition($order, 'collectSample', $user, ['sample_type' => $data['sample_type']]);

            return LabSample::create([
                'lab_order_id' => $order->id,
                'sample_no' => NumberSequence::next((string) $order->organisation_id, 'LAB_SAMPLE', null, 'SMP'),
                'sample_type' => $data['sample_type'],
                'collected_by' => $user->id,
                'collected_at' => now(),
                'condition_notes' => $data['condition_notes'] ?? null,
            ]);
        });
    }

    public function startProcessing(LabOrder $order, User $user): LabOrder
    {
        return $this->transition($order, 'startProcessing', $user);
    }

    /**
     * Enter results for the order's tests and complete it. Every test must
     * receive a value — a partially resulted order stays in PROCESSING.
     *
     * @param  list<array{lab_order_test_id: string, result_value: string, result_notes?: ?string, is_abnormal?: ?bool}>  $results
     */
    public function enterResults(LabOrder $order, array $results, User $user): LabOrder
    {
        return DB::transaction(function () use ($order, $results, $user) {
            if ($order->status !== 'PROCESSING') {
                throw new InvalidLabOrderStatusException("Lab order {$order->order_no} is {$order->status}; results are entered while it is PROCESSING.");
            }

            $byId = $order->tests->keyBy('id');
            foreach ($results as $result) {
                $test = $byId->get($result['lab_order_test_id'])
                    ?? throw new \DomainException('A result was supplied for a test that is not on this order.');

                $test->update([
                    'result_value' => $result['result_value'],
                    'result_notes' => $result['result_notes'] ?? null,
                    'is_abnormal' => $result['is_abnormal'] ?? null,
                    'result_entered_by' => $user->id,
                    'result_entered_at' => now(),
                ]);

                AuditLog::record('LAB_RESULT_ENTERED', 'lab_order_test', $test->id, [
                    'user_id' => $user->id,
                    'reference' => $order->order_no,
                    'after_json' => ['test' => $test->test_name, 'result' => $result['result_value']],
                ]);
            }

            if ($order->tests()->whereNull('result_value')->exists()) {
                return $order->fresh(['tests']);
            }

            $this->transition($order, 'complete', $user);
            $order->update(['completed_by' => $user->id, 'completed_at' => now()]);

            // The clinician picks the visit back up once results are in.
            if ($order->encounter->status === 'AWAITING_RESULTS') {
                $order->encounter->update(['status' => 'WAITING']);
            }

            return $order->fresh(['tests']);
        });
    }

    public function cancel(LabOrder $order, User $user, string $reason): LabOrder
    {
        return DB::transaction(function () use ($order, $user, $reason) {
            $this->transition($order, 'cancel', $user, ['reason' => $reason]);
            $order->update(['cancelled_by' => $user->id, 'cancelled_at' => now(), 'cancel_reason' => $reason]);

            // The visit no longer owes for tests that will never run.
            EncounterCharge::where('source_type', 'lab_order')->where('source_id', $order->id)
                ->where('status', 'PENDING')->update(['status' => 'WAIVED']);

            return $order;
        });
    }

    /** @param array<string, mixed> $context */
    private function transition(LabOrder $order, string $action, User $user, array $context = []): LabOrder
    {
        $rule = self::TRANSITIONS[$action];

        if (! in_array($order->status, $rule['from'], true)) {
            throw new InvalidLabOrderStatusException(
                "Lab order {$order->order_no} is {$order->status}; '{$action}' needs ".implode(' or ', $rule['from']).'.'
            );
        }

        $before = $order->status;
        $order->update(['status' => $rule['to']]);

        AuditLog::record('LAB_ORDER_'.strtoupper((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $action)), 'lab_order', $order->id, [
            'user_id' => $user->id,
            'reference' => $order->order_no,
            'before_json' => ['status' => $before],
            'after_json' => ['status' => $rule['to']] + $context,
        ]);

        return $order;
    }
}
