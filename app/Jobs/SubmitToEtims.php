<?php

namespace App\Jobs;

use App\Models\CustomerReturn;
use App\Models\Sale;
use App\Services\Tax\EtimsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Part 13.6 — "Transmission is asynchronous with retry, never blocking
 * checkout. A KRA outage must not stop the business from selling."
 *
 * A failed attempt is recorded on the document (FAILED + reason) and the
 * job is released with a growing delay; after the final attempt it stays
 * FAILED in the eTIMS queue screen for a manual retry.
 */
class SubmitToEtims implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public function __construct(public readonly string $type, public readonly string $documentId)
    {
        $this->tries = max(1, (int) config('etims.retry.tries', 5));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return array_values((array) config('etims.retry.backoff_seconds', [60, 300, 900, 3600]));
    }

    public function handle(EtimsService $etims): void
    {
        try {
            if ($this->type === 'credit_note') {
                $return = CustomerReturn::find($this->documentId);
                if ($return) {
                    $etims->submitCreditNote($return);
                }
            } else {
                $sale = Sale::find($this->documentId);
                if ($sale) {
                    $etims->submitSale($sale);
                }
            }
        } catch (\Throwable $e) {
            // The failure is already on the document; retry later, never
            // bubble into the request that posted the sale.
            if ($this->attempts() < $this->tries) {
                $delays = $this->backoff();
                $this->release($delays[min($this->attempts(), count($delays)) - 1] ?? 60);
            }
        }
    }
}
