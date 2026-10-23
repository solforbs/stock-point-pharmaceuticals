<?php

namespace App\Services\Tax\Etims;

/**
 * What KRA returns for a fiscalised document: the control code and number
 * the receipt prints, and the link its QR code opens.
 */
final class EtimsResult
{
    public function __construct(
        public readonly string $controlCode,
        public readonly string $invoiceNumber,
        public readonly ?string $qrPayload = null,
    ) {}
}
