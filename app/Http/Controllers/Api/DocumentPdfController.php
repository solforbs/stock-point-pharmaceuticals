<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\DeliveryNote;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Quotation;
use App\Models\Sale;
use App\Models\User;
use App\Services\Documents\PdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Part 16.6 — documents handed to customers as PDFs rather than printed from
 * a browser. They are rendered from the posted record every time, never
 * stored, so a reprint can never disagree with the books.
 */
class DocumentPdfController extends ApiController
{
    /** GET /api/sales/{sale}/pdf — the tax invoice for a posted sale. */
    public function invoice(Request $request, string $sale, PdfRenderer $pdf): Response
    {
        $this->requirePermission($request, 'sale.view');

        $sale = Sale::where('branch_id', $this->branchId($request))
            ->with(['customer', 'lines.product:id,code,name,strength,generic_name', 'lines.uom:id,code', 'lines.taxCode:id,code', 'lines.batchAllocations.batch:id,batch_number,expiry_date'])
            ->findOrFail($sale);

        // A sale has no direct payment relation: money is allocated to a
        // document, so the receipt lines come from the allocations.
        $payments = DB::table('payment_allocations as pa')
            ->join('payments as p', 'p.id', '=', 'pa.payment_id')
            ->where('pa.allocated_to_type', 'sale')->where('pa.allocated_to_id', $sale->id)
            ->get(['p.method', 'p.reference', 'pa.amount']);
        $amountPaid = $payments->reduce(fn (string $sum, $p) => bcadd($sum, (string) $p->amount, 4), '0');

        AuditLog::record('INVOICE_PRINTED', 'sale', $sale->id, ['reference' => $sale->doc_number]);

        return $pdf->render('pdf.invoice', [
            'title' => $sale->documentTitle($amountPaid),
            'sale' => $sale,
            'amountPaid' => $amountPaid,
            'balanceDue' => bccomp((string) $sale->grand_total, $amountPaid, 4) > 0 ? bcsub((string) $sale->grand_total, $amountPaid, 4) : '0',
            'vatAnalysis' => $this->vatAnalysis($sale),
            'amountInWords' => class_exists(\NumberFormatter::class) ? $this->amountInWords((string) $sale->grand_total) : null,
            'cashier' => User::where('id', $sale->user_id)->value('name'),
            'payments' => $payments,
            'money' => fn ($v) => number_format((float) $v, 2),
            'qty' => fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.'),
        ], $sale->doc_number, $sale->branch_id);
    }

    /**
     * The VAT summary KRA expects at the foot of an invoice: net and VAT per
     * rate, so zero-rated and exempt lines are visibly separate from 16%.
     *
     * @return list<array{label: string, net: string, vat: string}>
     */
    private function vatAnalysis(Sale $sale): array
    {
        $groups = [];
        foreach ($sale->lines as $line) {
            $label = $this->vatLabel($line->taxCode?->code, (string) $line->tax_rate);
            $net = bcsub((string) $line->line_total, (string) $line->tax_amount, 4);
            $groups[$label] ??= ['label' => $label, 'net' => '0', 'vat' => '0'];
            $groups[$label]['net'] = bcadd($groups[$label]['net'], $net, 4);
            $groups[$label]['vat'] = bcadd($groups[$label]['vat'], (string) $line->tax_amount, 4);
        }

        return array_values($groups);
    }

    private function vatLabel(?string $code, string $rate): string
    {
        $code = strtoupper((string) $code);

        return match (true) {
            str_contains($code, 'ZERO') => 'Zero-rated (0%)',
            str_contains($code, 'EX') => 'Exempt',
            bccomp($rate, '0', 3) > 0 => 'VAT '.rtrim(rtrim($rate, '0'), '.').'%',
            default => 'No VAT',
        };
    }

    private function amountInWords(string $amount): string
    {
        $shillings = (int) floor((float) $amount);
        $cents = (int) round(((float) $amount - $shillings) * 100);
        $words = ucfirst((string) (new \NumberFormatter('en', \NumberFormatter::SPELLOUT))->format($shillings)).' Kenya shillings';

        return $cents > 0 ? "{$words} and {$cents} cents only" : "{$words} only";
    }

    /** GET /api/delivery-notes/{note}/pdf — the note that travels with the goods. */
    public function deliveryNote(Request $request, string $note, PdfRenderer $pdf): Response
    {
        $this->requirePermission($request, 'warehouse.dispatch');

        $note = DeliveryNote::where('branch_id', $this->branchId($request))
            ->with(['salesOrder.customer', 'lines.product:id,code,name', 'lines.batchAllocations.batch:id,batch_number,expiry_date'])
            ->findOrFail($note);

        AuditLog::record('DELIVERY_NOTE_PRINTED', 'delivery_note', $note->id, ['reference' => $note->doc_number]);

        return $pdf->render('pdf.delivery-note', [
            'title' => 'Delivery note',
            'note' => $note,
            'qty' => fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.'),
        ], $note->doc_number, $note->branch_id);
    }

    /**
     * GET /api/goods-receipts/{receipt}/pdf — the goods received note: what
     * arrived, what was accepted into stock, and what went back and why.
     */
    public function goodsReceipt(Request $request, string $receipt, PdfRenderer $pdf): Response
    {
        $this->requirePermission($request, 'grn.create');

        $grn = GoodsReceipt::where('branch_id', $this->branchId($request))
            ->with(['supplier', 'store:id,code,name', 'receiver:id,name', 'purchaseOrder:id,doc_number', 'lines.product:id,code,name', 'lines.uom:id,code'])
            ->findOrFail($receipt);

        $total = '0.0000';
        foreach ($grn->lines as $line) {
            $total = bcadd($total, bcmul((string) $line->qty_accepted, (string) $line->unit_cost, 4), 4);
        }

        AuditLog::record('GRN_PRINTED', 'goods_receipt', $grn->id, ['reference' => $grn->doc_number]);

        return $pdf->render('pdf.goods-receipt', [
            'title' => $grn->status === 'POSTED' ? 'Goods received note' : 'Goods received note (draft)',
            'grn' => $grn,
            'total' => $total,
            'money' => fn ($v) => number_format((float) $v, 2),
            'qty' => fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.'),
        ], $grn->doc_number, $grn->branch_id);
    }

    /** GET /api/quotations/{quotation}/pdf — institutional wholesale price quotation. */
    public function quotation(Request $request, string $quotation, PdfRenderer $pdf): Response
    {
        $this->requirePermission($request, 'sale.view');

        $quote = Quotation::where('branch_id', $this->branchId($request))
            ->with(['customer', 'lines.product:id,code,name', 'lines.uom:id,code'])
            ->findOrFail($quotation);

        AuditLog::record('QUOTATION_PRINTED', 'quotation', $quote->id, ['reference' => $quote->doc_number]);

        return $pdf->render('pdf.quotation', [
            'title' => 'Wholesale quotation',
            'quotation' => $quote,
            'money' => fn ($v) => number_format((float) $v, 2),
            'qty' => fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.'),
        ], $quote->doc_number, $quote->branch_id);
    }

    /** GET /api/purchase-orders/{po}/pdf — official procurement purchase order. */
    public function purchaseOrder(Request $request, string $po, PdfRenderer $pdf): Response
    {
        $this->requireAnyPermission($request, ['po.create', 'po.approve']);

        $order = PurchaseOrder::where('branch_id', $this->branchId($request))
            ->with(['supplier', 'branch', 'lines.product:id,code,name', 'lines.uom:id,code'])
            ->findOrFail($po);

        $total = '0.0000';
        foreach ($order->lines as $line) {
            $lineTotal = bcmul((string) $line->qty_ordered, (string) $line->unit_price, 4);
            $total = bcadd($total, $lineTotal, 4);
        }

        AuditLog::record('PURCHASE_ORDER_PRINTED', 'purchase_order', $order->id, ['reference' => $order->doc_number]);

        return $pdf->render('pdf.purchase-order', [
            'title' => 'Purchase order',
            'order' => $order,
            'total' => $total,
            'money' => fn ($v) => number_format((float) $v, 2),
            'qty' => fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.'),
        ], $order->doc_number, $order->branch_id);
    }
}
