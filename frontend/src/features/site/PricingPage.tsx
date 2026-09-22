import { useQuery } from '@tanstack/react-query'
import { motion } from 'framer-motion'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Check, Infinity as InfinityIcon } from 'lucide-react'
import { apiGet } from '../../lib/api'
import { formatMoney } from '../../lib/money'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import type { Plan } from '../../lib/types'
import { CallToAction } from './SiteLayout'
import { Reveal, RevealItem } from './motion'
import { PageIntro } from './PageIntro'
import { FAQS } from './content'

type Interval = 'MONTHLY' | 'YEARLY'

/** The plans as the platform currently offers them, read from the public endpoint. */
export default function PricingPage() {
  const [interval, setInterval] = useState<Interval>('MONTHLY')
  const plans = useQuery({ queryKey: ['public', 'plans'], queryFn: () => apiGet<Plan[]>('/api/public/plans'), staleTime: 300_000 })
  const yearlyOffered = (plans.data ?? []).some((plan) => plan.price_yearly !== null)

  return (
    <>
      <PageIntro
        eyebrow="Pricing"
        title="One price, every module"
        body="No per-module licence and no charge for the reports. Pick the plan that matches your branches and your team, and start with a free seven-day trial."
      />

      <section className="mx-auto w-full max-w-6xl px-4 py-14 sm:px-6">
        {yearlyOffered && (
          <div className="mb-10 flex justify-center">
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

        {plans.isLoading && <LoadingSkeleton rows={4} />}
        {plans.error && <InlineError error={plans.error} />}

        {plans.data && (
          <Reveal className="grid items-start gap-6 lg:grid-cols-3" amount={0.1}>
            {plans.data.map((plan, index) => (
              <PlanCard key={plan.id} plan={plan} interval={interval} featured={index === 1} />
            ))}
          </Reveal>
        )}

        <p className="mt-8 text-center text-xs text-slate-500">
          Prices are per institution and exclude VAT. Setup, data loading and training are quoted separately —{' '}
          <Link to="/request-quote" className="font-semibold text-blue-600 hover:underline">ask us for a quote</Link>.
        </p>
      </section>

      <Faqs />
      <CallToAction title="Not sure which plan fits?" body="Tell us how many branches and tills you run and we will tell you which one you need — and which one you do not." />
    </>
  )
}

function PlanCard({ plan, interval, featured }: { plan: Plan; interval: Interval; featured: boolean }) {
  const price = interval === 'YEARLY' ? plan.price_yearly : plan.price_monthly
  const unavailable = interval === 'YEARLY' && plan.price_yearly === null

  return (
    <RevealItem
      hover
      className={`relative flex h-full flex-col rounded-3xl border bg-white p-7 transition-shadow ${
        featured ? 'border-blue-600 shadow-xl shadow-blue-600/10 lg:-translate-y-3' : 'border-slate-200/90 shadow-xs hover:shadow-lg'
      }`}
    >
      {featured && (
        <span className="absolute -top-3 left-7 rounded-full bg-blue-600 px-3 py-1 text-[11px] font-semibold uppercase tracking-wide text-white">
          Most chosen
        </span>
      )}
      <h2 className="font-display text-lg font-bold tracking-tight text-slate-900">{plan.name}</h2>
      {plan.description && <p className="mt-1 text-sm text-slate-500">{plan.description}</p>}

      <motion.div key={interval} initial={{ opacity: 0, y: 6 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.25 }} className="mt-6 flex items-baseline gap-1.5">
        {unavailable ? (
          <span className="text-sm font-medium text-slate-500">Offered monthly only</span>
        ) : (
          <>
            <span className="text-sm font-semibold text-slate-500">{plan.currency}</span>
            <span className="font-display text-4xl font-bold tracking-tight text-slate-900">{formatMoney(price ?? '0')}</span>
            <span className="text-sm text-slate-500">/{interval === 'YEARLY' ? 'year' : 'month'}</span>
          </>
        )}
      </motion.div>

      <dl className="mt-6 grid grid-cols-2 gap-3 border-y border-slate-100 py-4 text-sm">
        <Limit label="Branches" value={plan.max_branches} />
        <Limit label="Users" value={plan.max_users} />
      </dl>

      <ul className="mt-5 flex-1 space-y-2.5">
        {(plan.features ?? []).map((feature) => (
          <li key={feature} className="flex items-start gap-2.5 text-sm leading-relaxed text-slate-700">
            <Check className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" aria-hidden />
            {feature}
          </li>
        ))}
      </ul>

      <Link
        to="/request-quote"
        className={`mt-7 inline-flex items-center justify-center rounded-xl px-5 py-3 text-sm font-semibold transition-colors ${
          featured
            ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/25 hover:bg-blue-700'
            : 'border border-slate-300 text-slate-700 hover:border-slate-400 hover:bg-slate-50'
        }`}
      >
        Start with {plan.name}
      </Link>
    </RevealItem>
  )
}

function Limit({ label, value }: { label: string; value: number | null }) {
  return (
    <div>
      <dt className="text-xs text-slate-500">{label}</dt>
      <dd className="mt-0.5 flex items-center gap-1 font-display text-base font-semibold text-slate-900">
        {value === null ? (
          <>
            <InfinityIcon className="h-4 w-4 text-blue-600" aria-hidden /> Unlimited
          </>
        ) : (
          value
        )}
      </dd>
    </div>
  )
}

function Faqs() {
  return (
    <section className="border-y border-slate-200/70 bg-white">
      <div className="mx-auto w-full max-w-3xl px-4 py-16 sm:px-6 sm:py-20">
        <h2 className="text-center font-display text-3xl font-bold tracking-tight text-slate-900">Questions we are asked</h2>
        <div className="mt-10 divide-y divide-slate-100">
          {FAQS.map((faq) => (
            <details key={faq.question} className="group py-5">
              <summary className="flex cursor-pointer items-center justify-between gap-4 text-left font-display text-base font-semibold text-slate-900 marker:content-['']">
                {faq.question}
                <span className="shrink-0 text-xl leading-none text-blue-600 transition-transform group-open:rotate-45" aria-hidden>+</span>
              </summary>
              <p className="mt-3 text-sm leading-relaxed text-slate-600">{faq.answer}</p>
            </details>
          ))}
        </div>
      </div>
    </section>
  )
}
