<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\DeliveryNote;
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
            ->with(['customer', 'lines.product:id,code,name', 'lines.uom:id,code', 'lines.batchAllocations.batch:id,batch_number,expiry_date'])
            ->findOrFail($sale);

        // A sale has no direct payment relation: money is allocated to a
        // document, so the receipt lines come from the allocations.
        $payments = DB::table('payment_allocations as pa')
            ->join('payments as p', 'p.id', '=', 'pa.payment_id')
            ->where('pa.allocated_to_type', 'sale')->where('pa.allocated_to_id', $sale->id)
            ->get(['p.method', 'pa.amount']);

        AuditLog::record('INVOICE_PRINTED', 'sale', $sale->id, ['reference' => $sale->doc_number]);

        return $pdf->render('pdf.invoice', [
            'title' => $sale->status === 'VOIDED' ? 'Voided invoice' : 'Tax invoice',
            'sale' => $sale,
            'cashier' => User::where('id', $sale->user_id)->value('name'),
            'payments' => $payments,
            'money' => fn ($v) => number_format((float) $v, 2),
            'qty' => fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.'),
        ], $sale->doc_number, $sale->branch_id);
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
}
