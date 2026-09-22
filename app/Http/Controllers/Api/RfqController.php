<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Rfq;
use App\Services\Documents\PdfRenderer;
use App\Services\Notifications\Notifier;
use App\Services\Procurement\CompetitiveBidAnalyzer;
use App\Services\Procurement\RfqService;
use App\Services\Tenancy\TenantRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Supplier quotes and the competitive bid analysis (client item 19):
 * request for quotation → quotes → CBA → award to draft purchase orders.
 */
class RfqController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->requirePermission($request, 'rfq.view');

        return response()->json(
            Rfq::where('branch_id', $this->branchId($request))
                ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
                ->when($request->string('q')->trim()->isNotEmpty(), fn ($q) => $q->where(fn ($w) => $w
                    ->where('title', 'like', '%'.$request->string('q')->trim().'%')
                    ->orWhere('doc_number', 'like', '%'.$request->string('q')->trim().'%')))
                ->withCount(['lines', 'suppliers', 'suppliers as quotes_received_count' => fn ($q) => $q->where('quote_status', 'RECEIVED')])
                ->orderByDesc('created_at')->orderByDesc('id')
                ->paginate($request->integer('per_page', 25))
        );
    }

    public function show(Request $request, string $rfq): JsonResponse
    {
        $this->requirePermission($request, 'rfq.view');

        return response()->json($this->detail($this->find($request, $rfq)));
    }

    public function store(Request $request, RfqService $rfqs): JsonResponse
    {
        $this->requirePermission($request, 'rfq.manage');

        $data = $request->validate($this->rules(true));
        $rfq = $rfqs->create($data + ['branch_id' => $this->branchId($request), 'user_id' => $request->user()->id]);

        return response()->json($this->detail($rfq), 201);
    }

    public function update(Request $request, string $rfq, RfqService $rfqs): JsonResponse
    {
        $this->requirePermission($request, 'rfq.manage');

        $data = $request->validate($this->rules(false));
        $updated = $rfqs->update($this->find($request, $rfq), $data, $request->user()->id);

        return response()->json($this->detail($updated));
    }

    public function send(Request $request, string $rfq, RfqService $rfqs): JsonResponse
    {
        $this->requirePermission($request, 'rfq.manage');

        return response()->json($this->detail($rfqs->send($this->find($request, $rfq), $request->user()->id)));
    }

    /** PUT /api/rfqs/{rfq}/quotes/{supplier} — one supplier's quote, entered as received. */
    public function recordQuote(Request $request, string $rfq, string $supplier, RfqService $rfqs): JsonResponse
    {
        $this->requirePermission($request, 'rfq.manage');

        $data = $request->validate([
            'declined' => ['sometimes', 'boolean'],
            'quote_reference' => ['nullable', 'string', 'max:100'],
            'quote_date' => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'delivery_charge' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['exclude_if:declined,true', 'array'],
            'lines.*.rfq_line_id' => ['required', 'uuid'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.qty_available' => ['nullable', 'numeric', 'gt:0'],
            'lines.*.lead_time_days' => ['required', 'integer', 'min:0', 'max:365'],
            'lines.*.shelf_life_months' => ['nullable', 'integer', 'min:0', 'max:120'],
            'lines.*.notes' => ['nullable', 'string', 'max:255'],
        ]);

        $rfqs->recordQuote($this->find($request, $rfq), $supplier, $data, $request->user()->id);

        return response()->json($this->detail($this->find($request, $rfq)));
    }

    public function close(Request $request, string $rfq, RfqService $rfqs, Notifier $notifier): JsonResponse
    {
        $this->requirePermission($request, 'rfq.manage');

        $closed = $rfqs->close($this->find($request, $rfq), $request->user()->id);

        // Whoever awards hears that the analysis is ready for a decision.
        $notifier->toPermission(
            'rfq.award',
            $this->branchId($request),
            "Quotes for {$closed->doc_number} are ready to award",
            "{$closed->title}: ".$closed->suppliers()->where('quote_status', 'RECEIVED')->count().' quote(s) received. Open the bid analysis to award.',
            category: 'PURCHASE_ORDER',
            link: '/buy/supplier-quotes?rfq='.$closed->id,
            priority: 'NORMAL',
            exceptUserId: $request->user()->id,
        );

        return response()->json($this->detail($closed));
    }

    public function cancel(Request $request, string $rfq, RfqService $rfqs): JsonResponse
    {
        $this->requirePermission($request, 'rfq.manage');
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);

        return response()->json($this->detail($rfqs->cancel($this->find($request, $rfq), $request->user()->id, $data['reason'])));
    }

    /** GET /api/rfqs/{rfq}/analysis — the CBA matrix, scores and recommendation. */
    public function analysis(Request $request, string $rfq, CompetitiveBidAnalyzer $analyzer): JsonResponse
    {
        $this->requirePermission($request, 'rfq.view');

        $rfq = $this->find($request, $rfq);

        // After the award the analysis is the one the approver decided on.
        return response()->json($rfq->status === 'AWARDED' && $rfq->analysis_snapshot ? $rfq->analysis_snapshot : $analyzer->analyse($rfq));
    }

    /**
     * POST /api/rfqs/{rfq}/award — accept the recommendation (no lines) or
     * choose per line; departing from the recommendation needs a reason.
     */
    public function award(Request $request, string $rfq, RfqService $rfqs, Notifier $notifier): JsonResponse
    {
        $this->requirePermission($request, 'rfq.award');

        $data = $request->validate([
            'lines' => ['sometimes', 'array', 'min:1'],
            'lines.*.rfq_line_id' => ['required', 'uuid'],
            'lines.*.supplier_id' => ['nullable', 'uuid', TenantRules::exists('suppliers')],
            'justification' => ['nullable', 'string', 'max:2000'],
        ]);

        $choices = isset($data['lines'])
            ? collect($data['lines'])->mapWithKeys(fn ($line) => [$line['rfq_line_id'] => $line['supplier_id'] ?? null])->all()
            : null;

        $result = $rfqs->award($this->find($request, $rfq), $choices, $data['justification'] ?? null, $request->user()->id);
        $awarded = $result['rfq'];

        $notifier->toPermission(
            'po.approve',
            $this->branchId($request),
            "{$awarded->doc_number} awarded: ".count($result['purchase_orders']).' purchase order(s) to approve',
            collect($result['purchase_orders'])->map(fn ($po) => "{$po->doc_number} to {$po->supplier?->name}")->implode('; ').'.',
            category: 'PURCHASE_ORDER',
            link: '/buy/purchase-orders',
            priority: 'NORMAL',
            exceptUserId: $request->user()->id,
        );

        return response()->json($this->detail($awarded) + ['created_purchase_orders' => $result['purchase_orders']]);
    }

    /** GET /api/rfqs/{rfq}/suppliers/{supplier}/pdf — the request as sent to one supplier. */
    public function rfqPdf(Request $request, string $rfq, string $supplier, PdfRenderer $pdf): Response
    {
        $this->requirePermission($request, 'rfq.view');

        $rfq = $this->find($request, $rfq)->load(['lines.product:id,code,name,strength', 'lines.uom:id,code', 'branch', 'creator:id,name']);
        $invite = $rfq->suppliers()->with('supplier')->where('supplier_id', $supplier)->firstOrFail();

        AuditLog::record('RFQ_PRINTED', 'rfq', $rfq->id, ['reference' => $rfq->doc_number, 'after_json' => ['supplier' => $invite->supplier?->code]]);

        return $pdf->render('pdf.rfq', [
            'title' => 'Request for quotation',
            'rfq' => $rfq,
            'supplier' => $invite->supplier,
            'qty' => fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.'),
        ], $rfq->doc_number.'-'.$invite->supplier?->code, $rfq->branch_id);
    }

    /** GET /api/rfqs/{rfq}/summary-pdf — the CBA and award summary. */
    public function summaryPdf(Request $request, string $rfq, CompetitiveBidAnalyzer $analyzer, PdfRenderer $pdf): Response
    {
        $this->requirePermission($request, 'rfq.view');

        $rfq = $this->find($request, $rfq)->load(['awarder:id,name', 'creator:id,name', 'lines', 'purchaseOrders.supplier:id,name']);
        $analysis = $rfq->status === 'AWARDED' && $rfq->analysis_snapshot ? $rfq->analysis_snapshot : $analyzer->analyse($rfq);

        AuditLog::record('CBA_SUMMARY_PRINTED', 'rfq', $rfq->id, ['reference' => $rfq->doc_number]);

        $supplierNames = collect($analysis['suppliers'])->pluck('name', 'supplier_id')->all();

        return $pdf->render('pdf.cba-summary', [
            'title' => $rfq->status === 'AWARDED' ? 'Bid analysis and award' : 'Bid analysis (not yet awarded)',
            'rfq' => $rfq,
            'analysis' => $analysis,
            'supplierNames' => $supplierNames,
            'awardedBySupplier' => $rfq->lines->pluck('awarded_supplier_id', 'id')->all(),
            'money' => fn ($v) => number_format((float) $v, 2),
            'qty' => fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.'),
        ], $rfq->doc_number.'-CBA', $rfq->branch_id);
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(bool $creating): array
    {
        $required = $creating ? 'required' : 'sometimes';

        return [
            'title' => [$required, 'string', 'max:150'],
            'needed_by' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'weights' => ['sometimes', 'array'],
            'weights.price' => ['required_with:weights', 'numeric', 'min:0', 'max:100'],
            'weights.lead_time' => ['required_with:weights', 'numeric', 'min:0', 'max:100'],
            'weights.payment_terms' => ['required_with:weights', 'numeric', 'min:0', 'max:100'],
            'weights.supplier_record' => ['required_with:weights', 'numeric', 'min:0', 'max:100'],
            'lines' => [$required, 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'uuid', TenantRules::exists('products')],
            'lines.*.uom_id' => ['required', 'uuid', TenantRules::exists('units_of_measure')],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:255'],
            'supplier_ids' => [$required, 'array', 'min:1'],
            'supplier_ids.*' => ['required', 'uuid', 'distinct', TenantRules::exists('suppliers')],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Rfq $rfq): array
    {
        $rfq->load([
            'lines.product:id,code,name,strength,base_uom_id', 'lines.uom:id,code',
            'suppliers.supplier:id,code,name,email,phone,status,is_active,licence_expiry,payment_terms_days,lead_time_days',
            'suppliers.quoteLines',
            'purchaseOrders:id,doc_number,supplier_id,status,rfq_id,expected_date', 'purchaseOrders.supplier:id,name',
            'awarder:id,name', 'creator:id,name',
        ]);

        return $rfq->makeHidden('analysis_snapshot')->toArray();
    }

    private function find(Request $request, string $id): Rfq
    {
        return Rfq::where('branch_id', $this->branchId($request))->findOrFail($id);
    }
}
