<?php

namespace App\Services\Tax;

use App\Jobs\SubmitToEtims;
use App\Models\AuditLog;
use App\Models\CustomerReturn;
use App\Models\Sale;
use App\Services\Tax\Etims\EtimsDriver;
use App\Services\Tax\Etims\EtimsResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Part 13.6 — asynchronous, retried, never blocking. A sale carries its
 * eTIMS status from PENDING through SUBMITTED or FAILED; a credit note
 * transmits referencing the original control code.
 */
class EtimsService
{
    public const PENDING = 'PENDING';

    public const SUBMITTED = 'SUBMITTED';

    public const FAILED = 'FAILED';

    public const NOT_CONFIGURED = 'NOT_CONFIGURED';

    public function __construct(private readonly EtimsDriver $driver) {}

    /** Called inside the checkout transaction; the job runs only after commit. */
    public function queue(Sale $sale): void
    {
        $this->enqueue($sale, 'sale');
    }

    public function queueCreditNote(CustomerReturn $return): void
    {
        if (bccomp((string) $return->grand_total, '0', 4) <= 0) {
            return;
        }
        $this->enqueue($return, 'credit_note');
    }

    public function submitSale(Sale $sale): Sale
    {
        $this->transmit($sale, fn () => $this->driver->submitInvoice($this->invoicePayload($sale)));

        return $sale->fresh();
    }

    public function submitCreditNote(CustomerReturn $return): CustomerReturn
    {
        $this->transmit($return, fn () => $this->driver->submitCreditNote($this->creditNotePayload($return)));

        return $return->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function queueSummary(string $branchId): array
    {
        $sales = DB::table('sales')->where('branch_id', $branchId)->whereNotNull('etims_status')
            ->groupBy('etims_status')->selectRaw('etims_status, COUNT(*) as n')->pluck('n', 'etims_status');
        $notes = DB::table('customer_returns')->where('branch_id', $branchId)->whereNotNull('etims_status')
            ->groupBy('etims_status')->selectRaw('etims_status, COUNT(*) as n')->pluck('n', 'etims_status');

        $counts = [];
        foreach ([self::PENDING, self::SUBMITTED, self::FAILED, self::NOT_CONFIGURED] as $status) {
            $counts[strtolower($status)] = (int) ($sales[$status] ?? 0) + (int) ($notes[$status] ?? 0);
        }

        return $counts + ['enabled' => (bool) config('etims.enabled'), 'driver' => (string) config('etims.driver')];
    }

    /**
     * @return array<string, mixed>
     */
    public function invoicePayload(Sale $sale): array
    {
        $sale->loadMissing(['lines.product:id,code,name', 'customer:id,code,name,tax_status,exemption_ref']);

        return [
            'document_type' => 'INVOICE',
            'seller_pin' => config('etims.seller.pin'),
            'branch_code' => config('etims.seller.branch_code'),
            'invoice_number' => $sale->doc_number,
            'invoice_date' => $sale->posted_at->toIso8601String(),
            'sale_mode' => $sale->sale_mode,
            'customer' => $sale->customer ? ['code' => $sale->customer->code, 'name' => $sale->customer->name, 'tax_status' => $sale->customer->tax_status, 'exemption_ref' => $sale->customer->exemption_ref] : null,
            'lines' => $sale->lines->map(fn ($l) => [
                'line_number' => $l->line_number,
                'product_code' => $l->product?->code,
                'description' => $l->product?->name,
                'quantity' => (string) $l->qty,
                'unit_price' => (string) $l->unit_price,
                'discount_amount' => (string) $l->discount_amount,
                'tax_rate' => (string) $l->tax_rate,
                'tax_amount' => (string) $l->tax_amount,
                'line_total' => (string) $l->line_total,
                'is_bonus' => (bool) $l->is_bonus,
            ])->values()->all(),
            'subtotal' => (string) $sale->subtotal,
            'discount_total' => (string) $sale->discount_total,
            'tax_total' => (string) $sale->tax_total,
            'grand_total' => (string) $sale->grand_total,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function creditNotePayload(CustomerReturn $return): array
    {
        $return->loadMissing(['lines.product:id,code,name', 'sale:id,doc_number,etims_control_code,etims_invoice_number']);

        return [
            'document_type' => 'CREDIT_NOTE',
            'seller_pin' => config('etims.seller.pin'),
            'branch_code' => config('etims.seller.branch_code'),
            'credit_note_number' => $return->credit_note_number,
            'credit_note_date' => $return->posted_at?->toIso8601String(),
            'original_invoice_number' => $return->sale?->doc_number,
            'original_control_code' => $return->sale?->etims_control_code,
            'original_etims_invoice_number' => $return->sale?->etims_invoice_number,
            'lines' => $return->lines->map(fn ($l) => [
                'product_code' => $l->product?->code,
                'description' => $l->product?->name,
                'quantity_base' => (string) $l->qty_base,
                'unit_price' => (string) $l->unit_price,
                'tax_amount' => (string) $l->tax_amount,
                'line_total' => (string) $l->line_total,
            ])->values()->all(),
            'subtotal' => (string) $return->subtotal,
            'tax_total' => (string) $return->tax_total,
            'grand_total' => (string) $return->grand_total,
        ];
    }

    private function enqueue(Model $document, string $type): void
    {
        if (! config('etims.enabled')) {
            $document->forceFill(['etims_status' => self::NOT_CONFIGURED, 'etims_error' => 'eTIMS transmission is not enabled.'])->save();

            return;
        }

        $document->forceFill(['etims_status' => self::PENDING, 'etims_error' => null])->save();
        SubmitToEtims::dispatch($type, (string) $document->getKey())->afterCommit();
    }

    /**
     * @param  callable(): EtimsResult  $send
     */
    private function transmit(Model $document, callable $send): void
    {
        try {
            $result = $send();
        } catch (\Throwable $e) {
            $document->forceFill(['etims_status' => self::FAILED, 'etims_error' => mb_substr($e->getMessage(), 0, 250)])->save();
            AuditLog::record('ETIMS_FAILED', $document->getTable() === 'sales' ? 'sale' : 'customer_return', (string) $document->getKey(), [
                'reference' => $document->getAttribute('doc_number'), 'reason' => mb_substr($e->getMessage(), 0, 250),
            ]);

            throw $e;
        }

        $document->forceFill([
            'etims_status' => self::SUBMITTED,
            'etims_control_code' => $result->controlCode,
            'etims_invoice_number' => $result->invoiceNumber,
            'etims_submitted_at' => now(),
            'etims_error' => null,
        ])->save();

        AuditLog::record('ETIMS_SUBMITTED', $document->getTable() === 'sales' ? 'sale' : 'customer_return', (string) $document->getKey(), [
            'reference' => $document->getAttribute('doc_number'),
            'after_json' => ['control_code' => $result->controlCode, 'etims_invoice_number' => $result->invoiceNumber],
        ]);
    }
}
