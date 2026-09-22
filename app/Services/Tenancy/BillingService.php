<?php

namespace App\Services\Tenancy;

use App\Models\AuditLog;
use App\Models\Organisation;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentMismatchException extends \RuntimeException {}

/**
 * Paying for a plan through Paystack.
 *
 * Checkout records a PENDING payment and hands the browser Paystack's page.
 * The charge is then settled by whichever arrives first — the browser's
 * return (verify) or Paystack's webhook — and settling is idempotent, so the
 * second arrival changes nothing. A successful charge extends the paid
 * period by one interval from the later of today and the current period
 * end, so paying early never loses days. Renewals that Paystack charges on
 * its own (recurring plans) arrive through the webhook and are matched to
 * the institution by its Paystack customer.
 */
class BillingService
{
    public function __construct(private readonly PaystackClient $paystack) {}

    /**
     * @return array{authorization_url: string, reference: string, payment: SubscriptionPayment}
     */
    public function checkout(Organisation $organisation, Plan $plan, string $interval, User $user, string $callbackUrl): array
    {
        $amount = $plan->priceFor($interval);
        if ($amount === null) {
            throw new \InvalidArgumentException("The {$plan->name} plan has no ".strtolower($interval).' price.');
        }

        $payment = SubscriptionPayment::create([
            'organisation_id' => $organisation->id,
            'plan_id' => $plan->id,
            'billing_interval' => $interval,
            'reference' => 'SUB-'.Str::upper(Str::random(14)),
            'amount' => $amount,
            'currency' => $plan->currency,
            'status' => 'PENDING',
            'initiated_by' => $user->id,
        ]);

        $gateway = $this->paystack->initialize(
            $organisation->contact_email ?: $user->email,
            $this->subunit((string) $amount),
            $plan->currency,
            $payment->reference,
            $callbackUrl,
            ['organisation_id' => $organisation->id, 'plan_id' => $plan->id, 'billing_interval' => $interval, 'payment_id' => $payment->id],
            $plan->paystackPlanFor($interval),
        );

        AuditLog::record('SUBSCRIPTION_CHECKOUT_STARTED', 'subscription_payment', $payment->id, [
            'reference' => $payment->reference, 'after_json' => ['plan' => $plan->code, 'interval' => $interval, 'amount' => (string) $amount],
        ]);

        return ['authorization_url' => $gateway['authorization_url'], 'reference' => $payment->reference, 'payment' => $payment];
    }

    /** The browser's return from Paystack: confirm with Paystack, never trust the redirect. */
    public function verify(Organisation $organisation, string $reference): SubscriptionPayment
    {
        $payment = SubscriptionPayment::where('organisation_id', $organisation->id)->where('reference', $reference)->firstOrFail();

        return $payment->status === 'PENDING' ? $this->settle($this->paystack->verify($reference)) : $payment;
    }

    /**
     * A Paystack webhook, already signature-checked.
     *
     * @param  array<string, mixed>  $data
     */
    public function handleWebhook(string $event, array $data): void
    {
        match ($event) {
            'charge.success' => $this->settle($data),
            'subscription.create' => $this->attachPaystackSubscription($data),
            'subscription.disable', 'subscription.not_renew' => $this->markNotRenewing($data),
            default => null,
        };
    }

    /**
     * Records the outcome of one charge. Idempotent on the reference.
     *
     * @param  array<string, mixed>  $transaction  as Paystack reports it (verify or charge.success)
     */
    public function settle(array $transaction): SubscriptionPayment
    {
        return DB::transaction(function () use ($transaction) {
            $reference = (string) ($transaction['reference'] ?? '');
            $payment = SubscriptionPayment::where('reference', $reference)->lockForUpdate()->first()
                ?? $this->renewalPayment($transaction);

            if ($payment->status !== 'PENDING') {
                return $payment;
            }

            $succeeded = ($transaction['status'] ?? null) === 'success';
            if ($succeeded && ((int) ($transaction['amount'] ?? 0) !== $this->subunit((string) $payment->amount) || strtoupper((string) ($transaction['currency'] ?? '')) !== $payment->currency)) {
                // Charged a different amount than the plan costs: never applied.
                $payment->forceFill(['status' => 'FAILED', 'gateway_response' => $transaction])->save();
                AuditLog::record('SUBSCRIPTION_PAYMENT_MISMATCH', 'subscription_payment', $payment->id, ['reference' => $reference, 'after_json' => ['charged' => $transaction['amount'] ?? null, 'currency' => $transaction['currency'] ?? null]]);

                return $payment;
            }

            $payment->forceFill([
                'status' => $succeeded ? 'SUCCESS' : 'FAILED',
                'channel' => $transaction['channel'] ?? null,
                'paid_at' => $succeeded ? Carbon::parse($transaction['paid_at'] ?? now()) : null,
                'gateway_response' => $transaction,
            ])->save();

            if ($succeeded) {
                $subscription = $this->extend($payment, (string) data_get($transaction, 'customer.customer_code'));
                $payment->forceFill(['subscription_id' => $subscription->id])->save();
                AuditLog::record('SUBSCRIPTION_PAID', 'subscription', $subscription->id, [
                    'reference' => $payment->reference,
                    'after_json' => ['amount' => (string) $payment->amount, 'period_end' => $subscription->current_period_end?->toIso8601String()],
                ]);
            }

            return $payment;
        });
    }

    /** Extends (or starts) the institution's subscription for the plan paid for. */
    private function extend(SubscriptionPayment $payment, string $customerCode): Subscription
    {
        $organisation = Organisation::findOrFail($payment->organisation_id);

        // Changing plan ends the old subscription; the new one starts today.
        Subscription::where('organisation_id', $organisation->id)->where('status', 'ACTIVE')
            ->where(fn ($q) => $q->where('plan_id', '!=', $payment->plan_id)->orWhere('billing_interval', '!=', $payment->billing_interval))
            ->update(['status' => 'CANCELLED', 'cancelled_at' => now()]);

        $subscription = Subscription::firstOrNew([
            'organisation_id' => $organisation->id, 'plan_id' => $payment->plan_id, 'billing_interval' => $payment->billing_interval, 'status' => 'ACTIVE',
        ]);
        $from = $subscription->current_period_end && $subscription->current_period_end->isFuture() ? $subscription->current_period_end : now();
        $subscription->fill([
            'current_period_start' => $subscription->current_period_start ?? now(),
            'current_period_end' => $payment->billing_interval === 'YEARLY' ? $from->copy()->addYear() : $from->copy()->addMonth(),
            'paystack_customer_code' => $customerCode ?: $subscription->paystack_customer_code,
        ])->save();

        $organisation->refreshSubscriptionStatus();

        return $subscription;
    }

    /**
     * A charge Paystack made on its own (a recurring plan's renewal): match it
     * to the institution through the Paystack customer.
     *
     * @param  array<string, mixed>  $transaction
     */
    private function renewalPayment(array $transaction): SubscriptionPayment
    {
        $customerCode = (string) data_get($transaction, 'customer.customer_code');
        $subscription = $customerCode !== ''
            ? Subscription::where('paystack_customer_code', $customerCode)->orderByDesc('current_period_end')->first()
            : null;
        $organisationId = data_get($transaction, 'metadata.organisation_id') ?? $subscription?->organisation_id;
        if (! $organisationId || ! $subscription) {
            throw new PaymentMismatchException('A Paystack charge could not be matched to any institution.');
        }

        return SubscriptionPayment::create([
            'organisation_id' => $organisationId,
            'subscription_id' => $subscription->id,
            'plan_id' => $subscription->plan_id,
            'billing_interval' => $subscription->billing_interval,
            'reference' => (string) $transaction['reference'],
            'amount' => bcdiv((string) ($transaction['amount'] ?? 0), '100', 2),
            'currency' => strtoupper((string) ($transaction['currency'] ?? 'KES')),
            'status' => 'PENDING',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function attachPaystackSubscription(array $data): void
    {
        $customerCode = (string) data_get($data, 'customer.customer_code');
        if ($customerCode === '') {
            return;
        }
        Subscription::where('paystack_customer_code', $customerCode)->where('status', 'ACTIVE')->update([
            'paystack_subscription_code' => $data['subscription_code'] ?? null,
            'paystack_email_token' => $data['email_token'] ?? null,
        ]);
    }

    /**
     * Paystack stopped the recurring charge: the paid period still runs to its end.
     *
     * @param  array<string, mixed>  $data
     */
    private function markNotRenewing(array $data): void
    {
        $code = $data['subscription_code'] ?? null;
        if ($code) {
            Subscription::where('paystack_subscription_code', $code)->update(['cancelled_at' => now()]);
        }
    }

    private function subunit(string $amount): int
    {
        return (int) bcmul($amount, '100', 0);
    }
}
