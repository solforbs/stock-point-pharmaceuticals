<?php

namespace App\Services\Finance;

use App\Models\AuditLog;
use App\Models\NumberSequence;
use App\Models\SupplyRequest;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\DB;

/**
 * Non-pharmaceutical supplies (brooms, mops, cleaning products, stationery).
 * They are not stock: nothing to batch, expire or dispense. A request is
 * raised, approved by someone else, then bought and paid at once — the cost
 * is expensed (Dr Cleaning and office supplies, Cr Cash / Bank / M-PESA /
 * Petty cash) in the same step, so the books never carry a phantom asset.
 */
class SupplyRequestService
{
    public const CATEGORIES = ['CLEANING', 'STATIONERY', 'OFFICE', 'PACKAGING', 'UNIFORMS', 'OTHER'];

    public const PAID_FROM = ['PETTY_CASH', 'CASH', 'BANK', 'MPESA'];

    public function __construct(private readonly JournalPoster $journalPoster) {}

    /**
     * @param  array{needed_by?: ?string, notes?: ?string, lines: list<array{item: string, category?: ?string, qty: string|int|float, unit?: ?string, est_unit_cost?: string|int|float|null}>}  $data
     */
    public function create(string $organisationId, string $branchId, int $userId, array $data): SupplyRequest
    {
        return DB::transaction(function () use ($organisationId, $branchId, $userId, $data) {
            $request = SupplyRequest::create([
                'organisation_id' => $organisationId,
                'branch_id' => $branchId,
                'doc_number' => NumberSequence::next(organisationId: $organisationId, scope: 'SUPPLY_REQUEST', branchId: null, prefix: 'SR'),
                'status' => 'DRAFT',
                'needed_by' => $data['needed_by'] ?? null,
                'notes' => $data['notes'] ?? null,
                'requested_by' => $userId,
            ]);

            $total = '0.0000';
            foreach ($data['lines'] as $line) {
                $estimate = (string) ($line['est_unit_cost'] ?? 0);
                $request->lines()->create([
                    'item' => $line['item'],
                    'category' => $line['category'] ?? 'OTHER',
                    'qty' => (string) $line['qty'],
                    'unit' => $line['unit'] ?? 'pcs',
                    'est_unit_cost' => $estimate,
                ]);
                $total = bcadd($total, bcmul((string) $line['qty'], $estimate, 4), 4);
            }
            $request->update(['total_cost' => $total]);

            return $request->load('lines');
        });
    }

    public function submit(SupplyRequest $request): SupplyRequest
    {
        $this->requireStatus($request, ['DRAFT']);
        $request->update(['status' => 'PENDING_APPROVAL']);

        return $request->fresh('lines');
    }

    public function approve(SupplyRequest $request, int $userId): SupplyRequest
    {
        $this->requireStatus($request, ['PENDING_APPROVAL']);
        if ($request->requested_by === $userId) {
            throw new \DomainException('A supply request cannot be approved by the person who raised it.');
        }
        $request->update(['status' => 'APPROVED', 'approved_by' => $userId, 'approved_at' => now()]);
        AuditLog::record('SUPPLY_REQUEST_APPROVED', 'supply_request', $request->id, []);

        return $request->fresh('lines');
    }

    public function reject(SupplyRequest $request, int $userId, string $reason): SupplyRequest
    {
        $this->requireStatus($request, ['PENDING_APPROVAL']);
        $request->update(['status' => 'REJECTED', 'approved_by' => $userId, 'approved_at' => now(), 'reject_reason' => $reason]);

        return $request->fresh('lines');
    }

    /**
     * Records the purchase: the actual cost per line, who was paid and from where.
     *
     * @param  array{paid_from: string, supplier_id?: ?string, supplier_name?: ?string, payment_reference?: ?string, lines: array<string, string|int|float>}  $data  lines: line id => actual unit cost
     */
    public function purchase(SupplyRequest $request, int $userId, array $data): SupplyRequest
    {
        $this->requireStatus($request, ['APPROVED']);
        ChartOfAccountsSeeder::provisionAccounts($request->organisation_id);

        return DB::transaction(function () use ($request, $userId, $data) {
            $total = '0.0000';
            foreach ($request->lines as $line) {
                $actual = (string) ($data['lines'][$line->id] ?? $line->est_unit_cost);
                $line->update(['actual_unit_cost' => $actual]);
                $total = bcadd($total, bcmul((string) $line->qty, $actual, 4), 4);
            }
            if (bccomp($total, '0', 4) <= 0) {
                throw new \DomainException('Enter what the items actually cost before recording the purchase.');
            }

            $role = match ($data['paid_from']) {
                'PETTY_CASH' => 'PETTY_CASH',
                'BANK' => 'BANK',
                'MPESA' => 'MPESA_CLEARING',
                default => 'CASH',
            };

            if ($role === 'PETTY_CASH') {
                $held = app(PettyCashService::class)->balance($request->organisation_id, $request->branch_id);
                if (bccomp($total, $held, 4) > 0) {
                    throw new \DomainException('The petty cash float holds KES '.number_format((float) $held, 2).'; this purchase is KES '.number_format((float) $total, 2).'. Choose another source or top the float up.');
                }
            }

            $from = ! empty($data['supplier_name']) ? " from {$data['supplier_name']}" : '';
            $journal = $this->journalPoster->post([
                'organisation_id' => $request->organisation_id,
                'branch_id' => $request->branch_id,
                'entry_date' => now(),
                'source_doc_type' => 'supply_request',
                'source_doc_id' => $request->id,
                'narration' => "Supplies bought {$request->doc_number}{$from}",
                'posted_by' => $userId,
            ], [
                ['account_role' => 'SUPPLIES_EXPENSE', 'debit' => $total, 'narration' => "Supplies {$request->doc_number}"],
                ['account_role' => $role, 'credit' => $total],
            ]);

            $request->update([
                'status' => 'PURCHASED',
                'paid_from' => $data['paid_from'],
                'supplier_id' => $data['supplier_id'] ?? null,
                'supplier_name' => $data['supplier_name'] ?? null,
                'payment_reference' => $data['payment_reference'] ?? null,
                'total_cost' => $total,
                'journal_id' => $journal->id,
                'purchased_by' => $userId,
                'purchased_at' => now(),
            ]);
            AuditLog::record('SUPPLY_PURCHASED', 'supply_request', $request->id, ['after_json' => ['total' => $total, 'paid_from' => $data['paid_from']]]);

            return $request->fresh('lines');
        });
    }

    /**
     * @param  list<string>  $allowed
     */
    private function requireStatus(SupplyRequest $request, array $allowed): void
    {
        if (! in_array($request->status, $allowed, true)) {
            throw new \DomainException("Supply request {$request->doc_number} is {$request->status}; this step needs it to be ".implode(' or ', $allowed).'.');
        }
    }
}
