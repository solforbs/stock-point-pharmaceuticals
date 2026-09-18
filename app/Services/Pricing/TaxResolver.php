<?php

namespace App\Services\Pricing;

use App\Models\Customer;
use App\Models\Product;
use App\Models\TaxCode;
use Illuminate\Support\Carbon;

/**
 * Part 13 — nothing hardcoded. Resolution order: customer exemption →
 * product tax code → (no code) 0%. The rate is the effective-dated
 * tax_rates row for the quote date, and the resolved rate is stamped on
 * the line so history never re-values at today's rate.
 */
class TaxResolver
{
    /**
     * @return array{tax_code_id: ?string, tax_code: ?string, rate_pct: string, reason: string}
     */
    public function resolve(Product $product, ?Customer $customer, ?Carbon $asOf = null): array
    {
        $asOf ??= now();

        if ($customer && in_array($customer->tax_status, ['EXEMPT', 'ZERO_RATED'], true)) {
            $exemptionValid = $customer->exemption_expiry === null || $customer->exemption_expiry->gte($asOf->startOfDay());
            if ($exemptionValid) {
                return [
                    'tax_code_id' => $product->tax_code_id,
                    'tax_code' => $customer->tax_status === 'EXEMPT' ? 'VAT-EX' : 'VAT-ZERO',
                    'rate_pct' => '0.000',
                    'reason' => "Customer tax status {$customer->tax_status}".($customer->exemption_ref ? " ({$customer->exemption_ref})" : ''),
                ];
            }
        }

        if (! $product->tax_code_id) {
            return ['tax_code_id' => null, 'tax_code' => null, 'rate_pct' => '0.000', 'reason' => 'Product has no tax code'];
        }

        /** @var TaxCode|null $code */
        $code = TaxCode::find($product->tax_code_id);
        $rate = $code?->currentRate($asOf);

        if (! $code || ! $rate) {
            return [
                'tax_code_id' => $product->tax_code_id,
                'tax_code' => $code?->code,
                'rate_pct' => '0.000',
                'reason' => 'No effective tax rate for '.($code->code ?? 'tax code').' on '.$asOf->toDateString(),
            ];
        }

        return [
            'tax_code_id' => $code->id,
            'tax_code' => $code->code,
            'rate_pct' => (string) $rate->rate_pct,
            'reason' => "{$code->code} @ {$rate->rate_pct}% effective {$rate->effective_from->toDateString()}",
        ];
    }
}
