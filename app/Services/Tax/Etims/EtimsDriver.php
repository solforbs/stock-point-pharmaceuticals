<?php

namespace App\Services\Tax\Etims;

final class EtimsResult
{
    public function __construct(
        public readonly string $controlCode,
        public readonly string $invoiceNumber,
        public readonly ?string $qrPayload = null,
    ) {}
}

class EtimsTransmissionException extends \RuntimeException {}

class EtimsNotConfiguredException extends \RuntimeException {}

/**
 * Part 13.6 — the seam between the ERP and KRA. Every driver receives the
 * same payload and returns the control code the invoice must print.
 */
interface EtimsDriver
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function submitInvoice(array $payload): EtimsResult;

    /**
     * @param  array<string, mixed>  $payload  carries original_control_code
     */
    public function submitCreditNote(array $payload): EtimsResult;
}
