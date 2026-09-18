<?php

namespace App\Services\Tax\Etims;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Transmits to the configured OSCU/VSCU gateway. The exact field names of
 * the gateway are a deployment concern (KRA's integrators differ); this
 * driver posts the ERP's canonical payload and expects `control_code` and
 * `invoice_number` back. Adapt `mapResponse()` for the chosen integrator.
 */
class HttpDriver implements EtimsDriver
{
    public function submitInvoice(array $payload): EtimsResult
    {
        return $this->send(config('etims.http.invoice_path'), $payload);
    }

    public function submitCreditNote(array $payload): EtimsResult
    {
        return $this->send(config('etims.http.credit_note_path'), $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $path, array $payload): EtimsResult
    {
        $baseUrl = rtrim((string) config('etims.http.base_url'), '/');
        if ($baseUrl === '') {
            throw new EtimsNotConfiguredException('ETIMS_BASE_URL is not set.');
        }

        try {
            $response = Http::withToken((string) config('etims.http.token'))
                ->timeout((int) config('etims.http.timeout_seconds', 15))
                ->acceptJson()
                ->post($baseUrl.$path, $payload + ['device_serial' => config('etims.http.device_serial')]);
        } catch (ConnectionException $e) {
            throw new EtimsTransmissionException('KRA gateway unreachable: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new EtimsTransmissionException("KRA gateway returned HTTP {$response->status()}: ".mb_substr($response->body(), 0, 200));
        }

        return $this->mapResponse($response->json() ?? []);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function mapResponse(array $body): EtimsResult
    {
        $controlCode = $body['control_code'] ?? $body['data']['control_code'] ?? $body['rcptSign'] ?? null;
        $invoiceNumber = $body['invoice_number'] ?? $body['data']['invoice_number'] ?? $body['curRcptNo'] ?? null;
        if (! $controlCode || ! $invoiceNumber) {
            throw new EtimsTransmissionException('KRA gateway response carried no control code.');
        }

        return new EtimsResult((string) $controlCode, (string) $invoiceNumber, $body['qr_payload'] ?? $body['data']['qr_payload'] ?? null);
    }
}
