import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { apiGet } from '../../lib/api'
import { formatMoney } from '../../lib/money'
import { LoadingSkeleton } from '../../components/ui/States'
import { Pricing1, type PricingPlan } from '../../components/ui/pricing-1'
import type { Plan } from '../../lib/types'
import { CallToAction } from './SiteLayout'
import { PageIntro } from './PageIntro'
import { FAQS } from './content'

type Interval = 'MONTHLY' | 'YEARLY'


/** Maps a catalogue plan onto the pricing columns for the chosen billing interval. */
function toPricingPlan(plan: Plan, interval: Interval, popular: boolean): PricingPlan {
  const isEnterprise = plan.code === 'ENTERPRISE' || parseFloat(String(plan.price_monthly)) === 0
  const price = interval === 'YEARLY' ? plan.price_yearly : plan.price_monthly
  const unavailable = !isEnterprise && interval === 'YEARLY' && plan.price_yearly === null

  let formattedPrice = `${plan.currency} ${formatMoney(price ?? '0')}`
  let periodText: string | undefined = interval === 'YEARLY' ? 'yearly' : 'monthly'

  if (isEnterprise) {
    formattedPrice = 'Contact us'
    periodText = undefined
  } else if (unavailable) {
    formattedPrice = 'Monthly only'
    periodText = undefined
  }

  // Build key highlights with prominent user limit display
  const userHighlight = plan.max_users === null
    ? 'Unlimited user accounts (Pharmacists, Cashiers, Admins)'
    : '1 User account included'

  const branchHighlight = plan.max_branches === null
    ? 'Unlimited branches & warehouses'
    : `${plan.max_branches} ${plan.max_branches === 1 ? 'Branch / Counter' : 'Branches'}`

  return {
    title: plan.name,
    popular,
    description: plan.description ?? '',
    price: formattedPrice,
    period: periodText,
    features: [
      userHighlight,
      branchHighlight,
      ...(plan.features ?? []),
    ],
    ctaLabel: isEnterprise ? 'Contact Us' : `Start with ${plan.name}`,
    ctaHref: '/request-quote',
  }
}

const FALLBACK_PLANS: Plan[] = [
  {
    id: 'starter',
    code: 'STARTER',
    name: 'Starter',
    description: 'Complete pharmacy operating system for single counter chemists with 1 user login.',
    currency: 'KES',
    price_monthly: '1500.00' as any,
    price_yearly: '15000.00' as any,
    max_branches: 1,
    max_users: 1,
    features: [
      '100% Offline POS (Never stops selling during outages)',
      'Automated FEFO Expiry Picking (Oldest stock picked first)',
      'Expiry date risk alerts & low stock notifications',
      'M-Pesa reference recording & cash till reconciliation',
      'Thermal receipt printing (58mm / 80mm)',
      'Daily sales, gross margin & cashier shift reports',
      'KRA eTIMS invoice generation & QR codes',
      'Customer & Supplier Directory',
      'Purchase Orders, GRN & 3-way invoice matching',
      'Stock counts, adjustments & audit logs',
    ],
  },
  {
    id: 'pro',
    code: 'PROFESSIONAL',
    name: 'Pro',
    description: 'Everything in Starter with Unlimited Users and multi-staff role permissions.',
    currency: 'KES',
    price_monthly: '3000.00' as any,
    price_yearly: '30000.00' as any,
    max_branches: 3,
    max_users: null,
    features: [
      'All Features from Starter Included',
      'Role-based branch permissions & staff audit trails',
      'Multi-till cashier shifts & separate till balances',
      'Customer trade price tiers, discounts & credit limits',
      'Prescription dispensing verification & pharmacist sign-off',
      'Batch product recall customer traceback & waste logs',
      '12 Pre-loaded PPB Standard Operating Procedures (SOPs)',
      'Full double-entry accounting, real-time P&L & VAT reports',
    ],
  },
  {
    id: 'enterprise',
    code: 'ENTERPRISE',
    name: 'Enterprise',
    description: 'Dedicated server deployment, custom setup, complete data ownership & full managed hosting.',
    currency: 'KES',
    price_monthly: '0.00' as any,
    price_yearly: null,
    max_branches: null,
    max_users: null,
    features: [
      'All Pro Features Included',
      'Dedicated Cloud / On-Premise Server Setup',
      'Complete Code & Database Ownership (Sold & Handed Over Completely)',
      'Fully Managed by Our Engineering Team (Backups, Updates, 24/7 Monitoring)',
      'Unlimited Branches, Warehouses & Regional Depots',
      'Wholesale quotations, sales orders & delivery gate passes',
      'Cold-chain vaccine logs & custom ERP integrations',
      'Dedicated Account Manager & On-Site Staff Training',
    ],
  },
]

/** The plans as the platform currently offers them, read from the public endpoint. */
export default function PricingPage() {
  const [interval, setInterval] = useState<Interval>('MONTHLY')
  const plansQuery = useQuery({ queryKey: ['public', 'plans'], queryFn: () => apiGet<Plan[]>('/api/public/plans'), staleTime: 0, retry: 1 })
  const displayPlans = plansQuery.data && plansQuery.data.length > 0 ? plansQuery.data : FALLBACK_PLANS
  const yearlyOffered = displayPlans.some((plan) => plan.price_yearly !== null)

  return (
    <>
      <PageIntro
        eyebrow="Pharmacy Plans & Licensing"
        title="Predictable Pricing for Growing Pharmacies"
        body="One straightforward subscription covering your tills, batch inventory, and double-entry accounting. No hidden fees, no per-module add-ons, and full KRA eTIMS readiness."
      />

      <section className="mx-auto w-full max-w-7xl px-4 py-14 sm:px-6">
        {yearlyOffered && (
          <div className="mb-2 flex justify-center">
            <div className="inline-flex rounded-xl border border-slate-200 bg-white p-1">
              {(['MONTHLY', 'YEARLY'] as const).map((option) => (
                <button
                  key={option}
                  type="button"
                  onClick={() => setInterval(option)}
                  className={`rounded-lg px-4 py-2 text-sm font-semibold transition-colors cursor-pointer ${
                    interval === option ? 'bg-blue-600 text-white' : 'text-slate-600 hover:text-slate-900'
                  }`}
                >
                  {option === 'MONTHLY' ? 'Monthly' : 'Yearly'}
                  {option === 'YEARLY' && <span className="ml-1.5 text-[11px] font-medium opacity-80">2 months free</span>}
                </button>
              ))}
            </div>
          </div>
        )}

        {plansQuery.isLoading && !plansQuery.data && <LoadingSkeleton rows={4} />}

        <Pricing1
          heading="Simple and transparent pricing"
          plans={displayPlans.map((plan, index) => toPricingPlan(plan, interval, index === 1))}
        />

        <p className="mt-2 text-center text-xs text-slate-500">
          Prices are per pharmacy and exclude VAT. Setup, data loading and training are quoted separately —{' '}
          <Link to="/request-quote" className="font-semibold text-blue-600 hover:underline">ask us for a quote</Link>.
        </p>
      </section>

      <Faqs />
      <CallToAction title="Not sure which plan fits?" body="Tell us how many branches and tills you run and we will tell you which one you need — and which one you do not." />
    </>
  )
}

function Faqs() {
  return (
    <section className="border-y border-slate-200/70 bg-white">
      <div className="mx-auto w-full max-w-3xl px-4 py-16 sm:px-6 sm:py-20">
        <h2 className="text-center font-display text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl">Questions we are asked</h2>
        <div className="mt-10 divide-y divide-slate-100">
          {FAQS.map((faq) => (
            <details key={faq.question} className="group py-5">
              <summary className="flex cursor-pointer items-center justify-between gap-4 text-left font-display text-lg font-bold text-slate-900 marker:content-['']">
                {faq.question}
                <span className="shrink-0 text-xl leading-none text-blue-600 transition-transform group-open:rotate-45" aria-hidden>+</span>
              </summary>
              <p className="mt-3 leading-relaxed text-slate-600">{faq.answer}</p>
            </details>
          ))}
        </div>
      </div>
    </section>
  )
}
