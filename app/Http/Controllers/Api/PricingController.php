<?php

namespace App\Http\Controllers\Api;

use App\Models\Branch;
use App\Services\Pricing\PriceQuoteService;
use App\Services\Pricing\PricingSimulator;
use App\Services\Sales\SaleModes;
use App\Services\Tenancy\TenantRules;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PricingController extends ApiController
{
    /**
     * POST /api/pricing/quote — Part 4.12. The authoritative price; every
     * checkout must present the quote_id this returns.
     */
    public function quote(Request $request, PriceQuoteService $quotes): JsonResponse
    {
        $this->requirePermission($request, 'sale.create');

        $data = $request->validate([
            'sale_mode' => ['required', 'in:RETAIL,WHOLESALE,DISPENSING'],
            'store_id' => ['required', 'uuid', TenantRules::exists('stores')],
            'customer_id' => ['nullable', 'uuid', TenantRules::exists('customers')],
            'header_discount' => ['nullable', 'numeric', 'min:0'],
            'header_discount_reason' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_ref' => ['nullable', 'string', 'max:20'],
            'lines.*.product_id' => ['required', 'uuid', TenantRules::exists('products')],
            'lines.*.uom_id' => ['required', 'uuid', TenantRules::exists('units_of_measure')],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.requested_discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.requested_discount_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.requested_discount_reason' => ['nullable', 'string', 'max:255'],
            'lines.*.batch_id' => ['nullable', 'uuid', TenantRules::exists('product_batches')],
            'lines.*.override_reason' => ['nullable', 'string', 'max:255'],
            'lines.*.selling_price' => ['nullable', 'numeric', 'gt:0'],
        ]);

        // V6 Part 10.1 / Part 24.2 — the branch decides which modes exist;
        // leaving the terminal's default mode is a permission-gated switch.
        $branch = Branch::findOrFail($this->branchId($request));
        $enabledModes = SaleModes::enabledFor($branch);
        if (! in_array($data['sale_mode'], $enabledModes, true)) {
            return $this->error('MODE_DISABLED', "Branch {$branch->code} does not trade in {$data['sale_mode']} mode.", 422, ['enabled_modes' => $enabledModes]);
        }
        $defaultMode = SaleModes::defaultFor($branch);
        if ($data['sale_mode'] !== $defaultMode && ! $request->user()->can('sale.mode.switch')) {
            return $this->error('MODE_SWITCH_FORBIDDEN', "Switching the POS from {$defaultMode} to {$data['sale_mode']} needs the sale.mode.switch permission.", 403, ['default_mode' => $defaultMode]);
        }

        if ($data['sale_mode'] === 'WHOLESALE' && empty($data['customer_id'])) {
            return $this->error('CUSTOMER_REQUIRED', 'A wholesale quote needs a customer (Part 6.4 step 2).', 422);
        }

        foreach ($data['lines'] as $line) {
            if (! empty($line['batch_id'])) {
                $this->requirePermission($request, 'stock.fefo.override');
                if (empty($line['override_reason'])) {
                    return $this->error('OVERRIDE_REASON_REQUIRED', 'A FEFO override needs a reason (Part 7.4).', 422);
                }
            }
            // A selling price set at the till prices this sale only; the catalog is untouched.
            if (! empty($line['selling_price'])) {
                $this->requirePermission($request, 'sale.price.override');
            }
        }

        $quote = $quotes->quote($data + [
            'organisation_id' => $this->organisationId($request),
            'branch_id' => $this->branchId($request),
            'user_id' => $request->user()->id,
        ]);

        return response()->json($quote);
    }

    /**
     * POST /api/pricing/simulate — Part 4.10, what-if modelling. Nothing posts.
     */
    public function simulate(Request $request, PricingSimulator $simulator): JsonResponse
    {
        $this->requirePermission($request, 'price.simulate');
        $this->requirePermission($request, 'product.cost.view');

        $data = $request->validate([
            'cost' => ['required', 'numeric', 'min:0'],
            'list_price' => ['required', 'numeric', 'min:0'],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'discount_pct' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'min_margin_pct' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'bonus_buy_qty' => ['nullable', 'numeric', 'min:0'],
            'bonus_free_qty' => ['nullable', 'numeric', 'min:0'],
            'funded_by' => ['nullable', 'in:SUPPLIER,US'],
            'monthly_volume' => ['nullable', 'numeric', 'min:0'],
            'round_to' => ['nullable', 'numeric', 'min:0'],
        ]);

        return response()->json($simulator->simulate(array_map(fn ($v) => is_null($v) ? null : (string) $v, $data)));
    }
}
