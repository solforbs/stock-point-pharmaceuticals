<?php

namespace App\Services\Tax\Etims;

/** Transmission disabled: every attempt fails loudly rather than pretending. */
class NullDriver implements EtimsDriver
{
    public function submitInvoice(array $payload): EtimsResult
    {
        throw new EtimsNotConfiguredException('No eTIMS driver is configured (ETIMS_DRIVER).');
    }

    public function submitCreditNote(array $payload): EtimsResult
    {
        throw new EtimsNotConfiguredException('No eTIMS driver is configured (ETIMS_DRIVER).');
    }
}
