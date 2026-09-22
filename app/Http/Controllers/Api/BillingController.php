<?php

namespace App\Http\Controllers\Api;

use App\Models\Organisation;
use App\Models\Plan;
use App\Models\SubscriptionPayment;
use App\Services\Tenancy\BillingService;
use App\Services\Tenancy\PaystackClient;
use App\Services\Tenancy\PaystackException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * An institution's own plan: where it stands, what it can choose, and
 * paying through Paystack. Reachable even when the institution has lapsed
 * or been suspended, so it can always see why and pay.
 */
class BillingController extends ApiController
{
    /** GET /api/billing */
    public function show(Request $request, PaystackClient $paystack): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');
        $organisation = Organisation::findOrFail($this->organisationId($request));
        $current = $organisation->currentSubscription();

        return response()->json([
            'institution' => $organisation->only(['id', 'name', 'contact_email']),
            'access_state' => $organisation->accessState(),
            'is_complimentary' => $organisation->is_complimentary,
            'trial_ends_at' => $organisation->trial_ends_at?->toIso8601String(),
            'suspension_reason' => $organisation->suspended_at ? $organisation->suspension_reason : null,
            'subscription' => $current ? [
                'plan' => $current->plan?->only(['id', 'code', 'name']),
                'billing_interval' => $current->billing_interval,
                'current_period_end' => $current->current_period_end?->toIso8601String(),
                'renews_automatically' => $current->paystack_subscription_code !== null && $current->cancelled_at === null,
            ] : null,
            'plans' => Plan::where('is_active', true)->orderBy('sort_order')->orderBy('price_monthly')
                ->get(['id', 'code', 'name', 'description', 'currency', 'price_monthly', 'price_yearly', 'max_branches', 'max_users', 'features']),
            'payments' => SubscriptionPayment::where('organisation_id', $organisation->id)->with('plan:id,name')
                ->orderByDesc('created_at')->limit(24)
                ->get(['id', 'plan_id', 'billing_interval', 'reference', 'amount', 'currency', 'status', 'channel', 'paid_at', 'created_at']),
            'online_payment_available' => $paystack->isConfigured(),
        ]);
    }

    /** POST /api/billing/checkout — returns Paystack's payment page for the browser to open. */
    public function checkout(Request $request, BillingService $billing): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');
        $data = $request->validate([
            'plan_id' => ['required', 'uuid', Rule::exists('plans', 'id')->where('is_active', true)],
            'billing_interval' => ['required', 'in:MONTHLY,YEARLY'],
        ]);

        $organisation = Organisation::findOrFail($this->organisationId($request));
        if ($organisation->suspended_at) {
            return $this->error('INSTITUTION_SUSPENDED', 'A suspended institution cannot subscribe; contact the platform administrator.', 403);
        }

        try {
            $checkout = $billing->checkout($organisation, Plan::findOrFail($data['plan_id']), $data['billing_interval'], $request->user(), url('/billing/callback'));
        } catch (PaystackException $e) {
            return $this->error('PAYMENT_GATEWAY', $e->getMessage(), 502);
        } catch (\InvalidArgumentException $e) {
            return $this->error('INVALID_INPUT', $e->getMessage(), 422);
        }

        return response()->json(['authorization_url' => $checkout['authorization_url'], 'reference' => $checkout['reference']], 201);
    }

    /** POST /api/billing/verify — the browser is back from Paystack; confirm the charge with Paystack. */
    public function verify(Request $request, BillingService $billing): JsonResponse
    {
        $this->requirePermission($request, 'admin.settings');
        $data = $request->validate(['reference' => ['required', 'string', 'max:100']]);
        $organisation = Organisation::findOrFail($this->organisationId($request));

        try {
            $payment = $billing->verify($organisation, $data['reference']);
        } catch (PaystackException $e) {
            return $this->error('PAYMENT_GATEWAY', $e->getMessage(), 502);
        }

        return response()->json([
            'status' => $payment->status,
            'reference' => $payment->reference,
            'access_state' => $organisation->fresh()->accessState(),
        ]);
    }
}
