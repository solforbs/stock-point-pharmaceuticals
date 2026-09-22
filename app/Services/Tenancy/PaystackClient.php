<?php

namespace App\Services\Tenancy;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class PaystackException extends \RuntimeException {}

/**
 * The few Paystack calls billing needs. Amounts go to Paystack in the
 * currency's subunit (KES cents); the webhook is trusted only when its
 * HMAC-SHA512 signature matches the secret key.
 */
class PaystackClient
{
    public function isConfigured(): bool
    {
        return (string) config('services.paystack.secret_key') !== '';
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array{authorization_url: string, access_code: string, reference: string}
     */
    public function initialize(string $email, int $amountSubunit, string $currency, string $reference, string $callbackUrl, array $metadata, ?string $planCode = null): array
    {
        $payload = array_filter([
            'email' => $email,
            'amount' => $amountSubunit,
            'currency' => $currency,
            'reference' => $reference,
            'callback_url' => $callbackUrl,
            'metadata' => $metadata,
            'plan' => $planCode,
        ], fn ($v) => $v !== null);

        return $this->call('post', '/transaction/initialize', $payload);
    }

    /**
     * @return array<string, mixed> the transaction as Paystack reports it
     */
    public function verify(string $reference): array
    {
        return $this->call('get', '/transaction/verify/'.rawurlencode($reference));
    }

    public function validSignature(string $rawBody, ?string $signature): bool
    {
        $secret = (string) config('services.paystack.secret_key');

        return $secret !== '' && $signature !== null && hash_equals(hash_hmac('sha512', $rawBody, $secret), $signature);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function call(string $method, string $path, array $payload = []): array
    {
        if (! $this->isConfigured()) {
            throw new PaystackException('Online payment is not set up yet (PAYSTACK_SECRET_KEY is missing).');
        }

        try {
            $response = Http::withToken((string) config('services.paystack.secret_key'))
                ->acceptJson()->connectTimeout(10)->timeout(30)
                ->{$method}(rtrim((string) config('services.paystack.base_url'), '/').$path, $payload);
        } catch (ConnectionException) {
            throw new PaystackException('Paystack could not be reached just now. Nothing was charged; please try again in a moment.');
        }

        if (! $response->successful() || ! $response->json('status')) {
            throw new PaystackException('Paystack refused the request: '.($response->json('message') ?? 'HTTP '.$response->status()));
        }

        return (array) $response->json('data');
    }
}
