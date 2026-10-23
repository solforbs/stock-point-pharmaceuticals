<?php

namespace App\Services\Tax\Etims;

use App\Models\CustomerReturn;
use App\Models\Sale;

/** Transmission disabled: every attempt fails loudly rather than pretending. */
class NullDriver implements EtimsDriver
{
    public function submitInvoice(array $payload, ?Sale $sale = null): EtimsResult
    {
        throw new EtimsNotConfiguredException('No eTIMS driver is configured (ETIMS_DRIVER).');
    }

    public function submitCreditNote(array $payload, ?CustomerReturn $return = null): EtimsResult
    {
        throw new EtimsNotConfiguredException('No eTIMS driver is configured (ETIMS_DRIVER).');
    }
}
