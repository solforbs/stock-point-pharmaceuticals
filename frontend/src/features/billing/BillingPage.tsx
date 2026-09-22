import { useMutation, useQuery } from '@tanstack/react-query'
import { Check, CreditCard } from 'lucide-react'
import { useState } from 'react'
import { Card, Button } from '../../components/ui/primitives'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { apiGet, apiPost } from '../../lib/api'
import { formatDate, formatDateTime } from '../../lib/format'
import { formatMoney } from '../../lib/money'
import type { BillingInterval, BillingOverview, SubscriptionPaymentRow } from '../../lib/types'
import { ACCESS_LABEL } from './accessState'

/** The institution's plan: where it stands, the plans on offer, and paying through Paystack. */
export default function BillingPage() {
  const [interval, setBillingInterval] = useState<BillingInterval>('MONTHLY')
  const billing = useQuery({ queryKey: ['billing'], queryFn: () => apiGet<BillingOverview>('/api/billing') })

  const checkout = useMutation({
    mutationFn: (planId: string) => apiPost<{ authorization_url: string }>('/api/billing/checkout', { plan_id: planId, billing_interval: interval }),
    // Paystack's own page takes the payment; it sends the browser back to /billing/callback.
    onSuccess: ({ authorization_url }) => window.location.assign(authorization_url),
  })

  const b = billing.data
  const columns: Column<SubscriptionPaymentRow>[] = [
    { key: 'date', header: 'Date', render: (p) => formatDateTime(p.paid_at ?? p.created_at) },
    { key: 'plan', header: 'Plan', render: (p) => `${p.plan?.name ?? '—'}${p.billing_interval ? ` · ${p.billing_interval === 'YEARLY' ? 'yearly' : 'monthly'}` : ''}` },
    { key: 'reference', header: 'Reference', render: (p) => <span className="tabular text-xs">{p.reference}</span> },
    { key: 'channel', header: 'Paid by', render: (p) => p.channel?.replace('_', ' ') ?? '—' },
    { key: 'status', header: 'Status', render: (p) => <StatusBadge status={p.status} tone={p.status === 'SUCCESS' ? 'green' : p.status === 'FAILED' ? 'red' : 'amber'} /> },
    { key: 'amount', header: 'Amount', align: 'right', render: (p) => <MoneyCell value={p.amount} /> },
  ]

  return (
    <Page>
      <PageHeader parent="Admin" title="Plan & Billing" subtitle="Your institution's subscription. Payments are taken securely by Paystack (card, M-Pesa or bank)." />
      {billing.isLoading && <LoadingSkeleton />}
      {billing.isError && <InlineError error={billing.error} />}
      {b && (
        <div className="space-y-5">
          <Card>
            <div className="p-5 flex flex-wrap items-center gap-x-8 gap-y-3">
              <div>
                <div className="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-1">Status</div>
                <StatusBadge status={b.access_state} tone={ACCESS_LABEL[b.access_state].tone} label={ACCESS_LABEL[b.access_state].label} />
              </div>
              {b.is_complimentary && <p className="text-sm text-slate-600">This institution is on a complimentary account and is never billed.</p>}
              {b.access_state === 'TRIAL' && b.trial_ends_at && (
                <p className="text-sm text-slate-600">Your free trial ends on <strong>{formatDate(b.trial_ends_at)}</strong>. Choose a plan to keep working after it.</p>
              )}
              {b.subscription && (
                <p className="text-sm text-slate-600">
                  <strong>{b.subscription.plan?.name}</strong> ({b.subscription.billing_interval === 'YEARLY' ? 'yearly' : 'monthly'}), paid until{' '}
                  <strong>{formatDate(b.subscription.current_period_end)}</strong>
                  {b.subscription.renews_automatically ? ' · renews automatically' : ''}.
                </p>
              )}
              {b.access_state === 'LAPSED' && (
                <p className="text-sm text-amber-800">Your trial or subscription has ended. Everything stays visible, but nothing new can be recorded until a plan is paid for.</p>
              )}
              {b.access_state === 'SUSPENDED' && (
                <p className="text-sm text-rose-700">Suspended by the platform{b.suspension_reason ? `: ${b.suspension_reason}` : ''}. Contact the platform team.</p>
              )}
            </div>
          </Card>

          {!b.is_complimentary && b.access_state !== 'SUSPENDED' && (
            <>
              <div className="flex items-center justify-between gap-3 flex-wrap">
                <h2 className="text-lg font-semibold text-slate-900">Choose a plan</h2>
                <div className="flex items-center gap-1 p-1 bg-slate-100/80 rounded-xl border border-slate-200/60">
                  {(['MONTHLY', 'YEARLY'] as const).map((value) => (
                    <button
                      key={value}
                      type="button"
                      onClick={() => setBillingInterval(value)}
                      className={`h-8 px-4 rounded-lg text-xs font-bold transition-all cursor-pointer ${interval === value ? 'bg-white text-slate-900 shadow-xs border border-slate-200/80' : 'text-slate-600 hover:text-slate-900'}`}
                    >
                      {value === 'MONTHLY' ? 'Monthly' : 'Yearly (2 months free)'}
                    </button>
                  ))}
                </div>
              </div>
              {!b.online_payment_available && (
                <InlineError error={{ response: { status: 503, data: { message: 'Online payment is not switched on yet. Contact the platform team to subscribe.' } } }} />
              )}
              {checkout.isError && <InlineError error={checkout.error} />}
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                {b.plans.map((plan) => {
                  const price = interval === 'YEARLY' ? plan.price_yearly : plan.price_monthly
                  const current = b.subscription?.plan?.id === plan.id && b.subscription.billing_interval === interval
                  return (
                    <div key={plan.id} className={`ui-card p-5 flex flex-col ${current ? 'ring-2 ring-blue-500' : ''}`}>
                      <h3 className="font-display text-lg font-semibold text-slate-900">{plan.name}</h3>
                      {plan.description && <p className="text-xs text-slate-500 mt-0.5">{plan.description}</p>}
                      <div className="mt-3 mb-4">
                        {price ? (
                          <>
                            <span className="text-2xl font-black text-slate-900 tabular">{plan.currency} {formatMoney(price)}</span>
                            <span className="text-xs text-slate-500"> / {interval === 'YEARLY' ? 'year' : 'month'}</span>
                          </>
                        ) : (
                          <span className="text-sm text-slate-500">Not offered {interval === 'YEARLY' ? 'yearly' : 'monthly'}</span>
                        )}
                      </div>
                      <ul className="space-y-1.5 text-sm text-slate-600 flex-1">
                        <li className="flex gap-2"><Check size={14} className="text-emerald-600 mt-0.5 shrink-0" />{plan.max_branches ? `${plan.max_branches} branch${plan.max_branches > 1 ? 'es' : ''}` : 'Unlimited branches'}</li>
                        <li className="flex gap-2"><Check size={14} className="text-emerald-600 mt-0.5 shrink-0" />{plan.max_users ? `${plan.max_users} users` : 'Unlimited users'}</li>
                        {(plan.features ?? []).map((f) => (
                          <li key={f} className="flex gap-2"><Check size={14} className="text-emerald-600 mt-0.5 shrink-0" />{f}</li>
                        ))}
                      </ul>
                      <Button
                        variant={current ? 'secondary' : 'primary'}
                        size="md"
                        className="mt-5 w-full"
                        disabled={!price || !b.online_payment_available || checkout.isPending}
                        onClick={() => checkout.mutate(plan.id)}
                      >
                        <CreditCard size={14} className="mr-1.5" /> {current ? 'Pay for the next period' : 'Choose and pay'}
                      </Button>
                    </div>
                  )
                })}
              </div>
            </>
          )}

          <Card title="Payments">
            <DataTable columns={columns} rows={b.payments} rowKey={(p) => p.id} emptyTitle="No payments yet" />
          </Card>
        </div>
      )}
    </Page>
  )
}
