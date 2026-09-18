<?php

namespace App\Services\Tax\Etims;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Records every submission in the application log and issues a placeholder
 * control code, so the whole queue/retry/print path can be exercised before
 * KRA onboarding is complete. Control codes are prefixed LOG- so a printed
 * invoice can never be mistaken for a fiscalised one.
 */
class LogDriver implements EtimsDriver
{
    public function submitInvoice(array $payload): EtimsResult
    {
        Log::channel(config('logging.default'))->info('eTIMS invoice (log driver)', $payload);

        return new EtimsResult('LOG-'.Str::upper(Str::random(12)), 'LOG-INV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)));
    }

    public function submitCreditNote(array $payload): EtimsResult
    {
        Log::channel(config('logging.default'))->info('eTIMS credit note (log driver)', $payload);

        return new EtimsResult('LOG-'.Str::upper(Str::random(12)), 'LOG-CN-'.now()->format('YmdHis').'-'.Str::upper(Str::random(4)));
    }
}
