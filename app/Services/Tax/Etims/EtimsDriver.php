<?php

namespace App\Services\Tax\Etims;

use App\Models\CustomerReturn;
use App\Models\Sale;

/**
 * Part 13.6 — the seam between the ERP and KRA. Every driver receives the
 * same payload and returns the control code the invoice must print. The
 * document itself travels too, for a driver (OSCU) that files KRA's own
 * format and needs more than the canonical payload carries.
 */
interface EtimsDriver
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function submitInvoice(array $payload, ?Sale $sale = null): EtimsResult;

    /**
     * @param  array<string, mixed>  $payload  carries original_control_code
     */
    public function submitCreditNote(array $payload, ?CustomerReturn $return = null): EtimsResult;
}
