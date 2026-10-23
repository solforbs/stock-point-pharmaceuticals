<?php

namespace App\Services\Tax\Etims\Oscu;

use App\Models\EtimsDevice;
use App\Services\Tax\Etims\EtimsNotConfiguredException;
use App\Services\Tax\Etims\EtimsTransmissionException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Talks to KRA's OSCU API for one device. Every call is a JSON POST that
 * carries the device's PIN, branch id and — once initialised — its
 * communication key in the headers, and every answer is wrapped in
 * {resultCd, resultMsg, resultDt, data}: "000" is success, "001" is a
 * search that found nothing, anything else is KRA refusing the request.
 */
class OscuClient
{
    public const SUCCESS = '000';

    public const NO_DATA = '001';

    /**
     * @param  array<string, mixed>  $body
     * @return array{result_code: string, result_message: string, data: array<string, mixed>, raw: array<string, mixed>}
     */
    public function call(EtimsDevice $device, string $endpoint, array $body, bool $withKey = true): array
    {
        $baseUrl = rtrim((string) config("etims.oscu.urls.{$device->environment}"), '/');
        if ($baseUrl === '') {
            throw new EtimsNotConfiguredException("No eTIMS OSCU address is set for the {$device->environment} environment.");
        }
        if ($withKey && ! $device->cmc_key) {
            throw new EtimsNotConfiguredException("The eTIMS device for branch {$device->bhf_id} has not been initialised yet.");
        }

        $headers = ['tin' => $device->tin, 'bhfId' => $device->bhf_id];
        if ($withKey) {
            $headers['cmcKey'] = (string) $device->cmc_key;
        }

        try {
            $response = $this->request()->withHeaders($headers)->post($baseUrl.'/'.ltrim($endpoint, '/'), $body);
        } catch (ConnectionException $e) {
            throw new EtimsTransmissionException('KRA eTIMS could not be reached: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            throw new EtimsTransmissionException("KRA eTIMS returned HTTP {$response->status()}: ".mb_substr($response->body(), 0, 200));
        }

        $json = (array) ($response->json() ?? []);
        $resultCode = (string) ($json['resultCd'] ?? '');
        $resultMessage = (string) ($json['resultMsg'] ?? '');

        if ($resultCode !== self::SUCCESS && $resultCode !== self::NO_DATA) {
            throw new EtimsRejectedException($resultCode, $resultMessage === '' ? 'KRA eTIMS refused the request.' : $resultMessage, $json);
        }

        return [
            'result_code' => $resultCode,
            'result_message' => $resultMessage,
            'data' => (array) ($json['data'] ?? []),
            'raw' => $json,
        ];
    }

    private function request(): PendingRequest
    {
        $request = Http::timeout((int) config('etims.oscu.timeout_seconds', 20))->acceptJson()->asJson();

        $token = $this->gatewayToken();

        return $token === null ? $request : $request->withToken($token);
    }

    /**
     * A bearer token for KRA's GavaConnect gateway, when one is configured;
     * cached until shortly before it expires.
     */
    private function gatewayToken(): ?string
    {
        $tokenUrl = (string) config('etims.oscu.gateway.token_url');
        $key = (string) config('etims.oscu.gateway.consumer_key');
        $secret = (string) config('etims.oscu.gateway.consumer_secret');
        if ($tokenUrl === '' || $key === '' || $secret === '') {
            return null;
        }

        return Cache::remember('etims:gateway-token:'.sha1($key), now()->addMinutes(50), function () use ($tokenUrl, $key, $secret) {
            try {
                $response = Http::timeout((int) config('etims.oscu.timeout_seconds', 20))
                    ->withBasicAuth($key, $secret)
                    ->acceptJson()
                    ->get($tokenUrl, ['grant_type' => 'client_credentials']);
            } catch (ConnectionException $e) {
                throw new EtimsTransmissionException('KRA gateway could not be reached for a token: '.$e->getMessage(), 0, $e);
            }

            $token = $response->json('access_token');
            if (! $response->successful() || ! is_string($token) || $token === '') {
                throw new EtimsTransmissionException("KRA gateway refused the consumer key and secret (HTTP {$response->status()}).");
            }

            return $token;
        });
    }
}
