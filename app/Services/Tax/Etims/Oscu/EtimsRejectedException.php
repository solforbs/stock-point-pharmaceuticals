<?php

namespace App\Services\Tax\Etims\Oscu;

use App\Services\Tax\Etims\EtimsTransmissionException;

/**
 * KRA answered, but refused: the result code and message say why.
 */
class EtimsRejectedException extends EtimsTransmissionException
{
    /**
     * @param  array<string, mixed>  $response
     */
    public function __construct(public readonly string $resultCode, string $message, public readonly array $response = [])
    {
        parent::__construct("KRA eTIMS {$resultCode}: {$message}");
    }
}
