import { useMutation, useQuery } from '@tanstack/react-query'
import { ArrowLeft, CheckCircle2, Mail } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Field, Input, Select, Textarea } from '../../components/ui/primitives'
import { InlineError } from '../../components/ui/States'
import { WhatsAppIcon } from '../site/BrandIcons'
import { apiGet, api, ensureCsrfCookie, getApiError } from '../../lib/api'
import { formatMoney } from '../../lib/money'
import type { Plan } from '../../lib/types'
import { PublicShell } from './PublicShell'

type Form = {
  institution_name: string
  contact_name: string
  email: string
  phone: string
  town: string
  branches_count: string
  users_count: string
  plan_id: string
  message: string
}

type Channel = 'whatsapp' | 'email'

const EMPTY: Form = {
  institution_name: '',
  contact_name: '',
  email: '',
  phone: '',
  town: '',
  branches_count: '',
  users_count: '',
  plan_id: '',
  message: '',
}

const SALES_WHATSAPP_NUMBER = '254725996394'
const SALES_EMAIL = 'info@solforbs.com'

function buildWhatsAppUrl(form: Form, planName?: string) {
  const lines = [
    `*Hello Stockpoint Solforbs Team*,`,
    `I would like to request a quote & demo for my healthcare institution:`,
    ``,
    `• *Institution:* ${form.institution_name.trim()}`,
    `• *Contact Person:* ${form.contact_name.trim()}`,
    `• *Email:* ${form.email.trim()}`,
    form.phone.trim() ? `• *Phone:* ${form.phone.trim()}` : null,
    form.town.trim() ? `• *Town/County:* ${form.town.trim()}` : null,
    form.branches_count ? `• *Branches:* ${form.branches_count}` : null,
    form.users_count ? `• *Staff Users:* ${form.users_count}` : null,
    planName ? `• *Interested Plan:* ${planName}` : null,
    form.message.trim() ? `• *Notes:* ${form.message.trim()}` : null,
    ``,
    `Please share pricing details and arrange our onboarding. Thank you!`,
  ]
    .filter(Boolean)
    .join('\n')

  return `https://wa.me/${SALES_WHATSAPP_NUMBER}?text=${encodeURIComponent(lines)}`
}

/** An institution asks for a quote; the platform reviews it, alerts info@solforbs.com, and supports direct WhatsApp dialogue. */
export default function RequestQuotePage() {
  const [form, setForm] = useState<Form>(EMPTY)
  const [channel, setChannel] = useState<Channel>('whatsapp')
  const [submittedChannel, setSubmittedChannel] = useState<Channel>('whatsapp')

  const set = (patch: Partial<Form>) => setForm((f) => ({ ...f, ...patch }))
  const plans = useQuery({ queryKey: ['public', 'plans'], queryFn: () => apiGet<Plan[]>('/api/public/plans') })

  const selectedPlan = plans.data?.find((p) => p.id === form.plan_id)
  const selectedPlanLabel = selectedPlan
    ? `${selectedPlan.name} (${selectedPlan.currency} ${formatMoney(selectedPlan.price_monthly)}/mo)`
    : undefined

  const submit = useMutation({
    meta: { silent: true },
    mutationFn: async (chosenChannel: Channel) => {
      setSubmittedChannel(chosenChannel)
      await ensureCsrfCookie()

      const channelPrefix =
        chosenChannel === 'whatsapp'
          ? `[Enquiry Channel: WhatsApp Sales (+254 725 996 394)]`
          : `[Enquiry Channel: Official Email (${SALES_EMAIL})]`

      const fullMessage = form.message.trim()
        ? `${channelPrefix}\n\n${form.message.trim()}`
        : channelPrefix

      await api.post('/api/public/quote-requests', {
        ...form,
        phone: form.phone || null,
        town: form.town || null,
        branches_count: form.branches_count ? Number(form.branches_count) : null,
        users_count: form.users_count ? Number(form.users_count) : null,
        plan_id: form.plan_id || null,
        message: fullMessage,
      })

      if (chosenChannel === 'whatsapp') {
        const waUrl = buildWhatsAppUrl(form, selectedPlanLabel)
        window.open(waUrl, '_blank')
      }
    },
  })

  const err = submit.isError ? getApiError(submit.error) : null
  const valid = form.institution_name.trim() && form.contact_name.trim() && /.+@.+\..+/.test(form.email)

  if (submit.isSuccess) {
    const waUrl = buildWhatsAppUrl(form, selectedPlanLabel)

    return (
      <PublicShell title="Enquiry received!">
        <div className="flex flex-col items-center text-center gap-4 py-3">
          <div className="w-14 h-14 rounded-full bg-emerald-50 border border-emerald-200 flex items-center justify-center text-emerald-600 shadow-sm">
            <CheckCircle2 className="w-8 h-8" />
          </div>

          <div className="space-y-2">
            <h2 className="text-lg font-bold text-slate-900 tracking-tight font-display">
              {submittedChannel === 'whatsapp' ? 'Connecting to WhatsApp…' : 'Quote request logged'}
            </h2>
            <p className="text-sm text-slate-600 max-w-md">
              Thank you, <strong>{form.contact_name}</strong>. Your details for{' '}
              <strong>{form.institution_name}</strong> have been recorded and forwarded directly to{' '}
              <strong className="text-slate-900 font-semibold">{SALES_EMAIL}</strong>. A confirmation has also been sent to{' '}
              <strong>{form.email}</strong>.
            </p>
          </div>

          {submittedChannel === 'whatsapp' ? (
            <div className="w-full bg-emerald-50/80 border border-emerald-200 rounded-xl p-4 text-left space-y-3 mt-1">
              <div className="flex items-start gap-2.5">
                <WhatsAppIcon className="w-5 h-5 fill-emerald-600 shrink-0 mt-0.5" />
                <div className="text-xs text-emerald-950">
                  <span className="font-semibold block text-emerald-900">WhatsApp message generated</span>
                  If WhatsApp did not open automatically in a new window, click the button below to send your pre-filled inquiry.
                </div>
              </div>
              <a
                href={waUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="w-full inline-flex items-center justify-center gap-2 py-2.5 px-4 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-medium text-xs tracking-wide shadow-xs transition-colors"
              >
                <WhatsAppIcon className="w-4 h-4 fill-current" />
                Open WhatsApp Chat (0725 996 394)
              </a>
            </div>
          ) : (
            <div className="w-full bg-slate-50 border border-slate-200 rounded-xl p-4 text-left space-y-2.5 mt-1">
              <div className="text-xs text-slate-600">
                <span className="font-semibold text-slate-900 block mb-0.5">Need a faster response?</span>
                Our team monitors WhatsApp enquiries continuously for instant quotes and live demo scheduling.
              </div>
              <a
                href={waUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-2 py-2 px-3.5 rounded-lg border border-emerald-300 bg-white hover:bg-emerald-50 text-emerald-700 font-medium text-xs transition-colors shadow-2xs"
              >
                <WhatsAppIcon className="w-3.5 h-3.5 fill-current" />
                Chat with sales on WhatsApp (0725 996 394)
              </a>
            </div>
          )}

          <div className="pt-2">
            <Link
              to="/home"
              className="inline-flex items-center gap-1.5 text-xs font-semibold text-blue-600 hover:text-blue-700 hover:underline"
            >
              <ArrowLeft className="w-3.5 h-3.5" /> Return to Stockpoint Homepage
            </Link>
          </div>
        </div>
      </PublicShell>
    )
  }

  return (
    <PublicShell
      wide
      title="Request a quote or demo"
      subtitle="Tell us about your pharmacy or healthcare institution. Enquire directly via WhatsApp for fastest response, or receive an official quote via email."
    >
      <form
        className="grid grid-cols-1 sm:grid-cols-2 gap-3"
        onSubmit={(e) => {
          e.preventDefault()
          if (valid && !submit.isPending) {
            submit.mutate(channel)
          }
        }}
      >
        <Field label="Institution name" required className="sm:col-span-2" error={err?.errors.institution_name?.[0]}>
          <Input
            value={form.institution_name}
            onChange={(e) => set({ institution_name: e.target.value })}
            placeholder="e.g. Lodwar Community Chemist or Nairobi Central Pharmacy"
          />
        </Field>

        <Field label="Your name" required error={err?.errors.contact_name?.[0]}>
          <Input
            value={form.contact_name}
            onChange={(e) => set({ contact_name: e.target.value })}
            placeholder="e.g. Dr. Jane Mwangi"
          />
        </Field>

        <Field label="Email address" required error={err?.errors.email?.[0]}>
          <Input
            type="email"
            value={form.email}
            onChange={(e) => set({ email: e.target.value })}
            placeholder="e.g. pharmacy@example.co.ke"
          />
        </Field>

        <Field label="Phone number">
          <Input
            value={form.phone}
            onChange={(e) => set({ phone: e.target.value })}
            placeholder="e.g. 0722 000 000"
          />
        </Field>

        <Field label="Town / County">
          <Input
            value={form.town}
            onChange={(e) => set({ town: e.target.value })}
            placeholder="e.g. Nairobi, Eldoret, Kisumu"
          />
        </Field>

        <Field label="Number of branches">
          <Input
            inputMode="numeric"
            value={form.branches_count}
            onChange={(e) => set({ branches_count: e.target.value.replace(/\D/g, '') })}
            placeholder="e.g. 1"
          />
        </Field>

        <Field label="Staff who will sign in">
          <Input
            inputMode="numeric"
            value={form.users_count}
            onChange={(e) => set({ users_count: e.target.value.replace(/\D/g, '') })}
            placeholder="e.g. 3"
          />
        </Field>

        <Field label="Plan you are interested in" className="sm:col-span-2">
          <Select value={form.plan_id} onChange={(e) => set({ plan_id: e.target.value })}>
            <option value="">Not sure yet (I want a recommendation)</option>
            {(plans.data ?? []).map((p) => (
              <option key={p.id} value={p.id}>
                {p.name} — {p.currency} {formatMoney(p.price_monthly)} / month
              </option>
            ))}
          </Select>
        </Field>

        <Field label="Special requirements or questions (optional)" className="sm:col-span-2">
          <Textarea
            rows={3}
            value={form.message}
            onChange={(e) => set({ message: e.target.value })}
            placeholder="Tell us about your setup (e.g. POS integration, existing ERP migration, PPB compliance, multi-branch synchronization)..."
          />
        </Field>

        {/* Enquiry Channel Selection */}
        <div className="sm:col-span-2 pt-2">
          <div className="flex items-center justify-between mb-2">
            <span className="text-xs font-bold uppercase tracking-wider text-slate-700">
              How would you prefer to enquire?
            </span>
            <span className="text-[11px] text-slate-500">
              All requests are saved & sent to <span className="font-semibold text-slate-700">{SALES_EMAIL}</span>
            </span>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
            <button
              type="button"
              onClick={() => setChannel('whatsapp')}
              className={`flex items-start gap-3 p-3 rounded-xl border text-left transition-all ${
                channel === 'whatsapp'
                  ? 'border-emerald-500 bg-emerald-50/70 ring-2 ring-emerald-500/20 shadow-xs'
                  : 'border-slate-200 bg-white hover:border-slate-300'
              }`}
            >
              <div
                className={`p-2 rounded-lg shrink-0 transition-colors ${
                  channel === 'whatsapp' ? 'bg-emerald-600 text-white' : 'bg-slate-100 text-slate-600'
                }`}
              >
                <WhatsAppIcon className="w-5 h-5 fill-current" />
              </div>
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-1.5 flex-wrap">
                  <span className="text-xs font-bold text-slate-900">Enquire via WhatsApp</span>
                  <span className="text-[9px] font-bold uppercase px-1.5 py-0.2 rounded-full bg-emerald-100 text-emerald-800 tracking-wide">
                    Fastest
                  </span>
                </div>
                <p className="text-[11px] text-slate-500 leading-snug mt-0.5">
                  Direct message to <span className="font-medium text-slate-700">0725 996 394</span> for instant answers and demo booking.
                </p>
              </div>
            </button>

            <button
              type="button"
              onClick={() => setChannel('email')}
              className={`flex items-start gap-3 p-3 rounded-xl border text-left transition-all ${
                channel === 'email'
                  ? 'border-blue-500 bg-blue-50/70 ring-2 ring-blue-500/20 shadow-xs'
                  : 'border-slate-200 bg-white hover:border-slate-300'
              }`}
            >
              <div
                className={`p-2 rounded-lg shrink-0 transition-colors ${
                  channel === 'email' ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-600'
                }`}
              >
                <Mail className="w-5 h-5" />
              </div>
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-1.5 flex-wrap">
                  <span className="text-xs font-bold text-slate-900">Enquire via Email</span>
                  <span className="text-[9px] font-bold uppercase px-1.5 py-0.2 rounded-full bg-blue-100 text-blue-800 tracking-wide">
                    Official
                  </span>
                </div>
                <p className="text-[11px] text-slate-500 leading-snug mt-0.5">
                  Delivered to <span className="font-medium text-slate-700">{SALES_EMAIL}</span>. Official proposal sent to your email.
                </p>
              </div>
            </button>
          </div>
        </div>

        {err && Object.keys(err.errors).length === 0 && (
          <InlineError error={submit.error} className="sm:col-span-2" />
        )}

        <div className="sm:col-span-2 pt-2">
          {channel === 'whatsapp' ? (
            <button
              type="submit"
              disabled={!valid || submit.isPending}
              className="w-full inline-flex items-center justify-center gap-2 py-3 px-5 rounded-xl text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-700 active:scale-[0.99] shadow-md shadow-emerald-600/20 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
            >
              <WhatsAppIcon className="w-4 h-4 fill-current" />
              {submit.isPending ? 'Logging request…' : 'Enquire via WhatsApp (0725 996 394)'}
            </button>
          ) : (
            <button
              type="submit"
              disabled={!valid || submit.isPending}
              className="w-full inline-flex items-center justify-center gap-2 py-3 px-5 rounded-xl text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 active:scale-[0.99] shadow-md shadow-blue-600/20 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
            >
              <Mail className="w-4 h-4" />
              {submit.isPending ? 'Sending request…' : `Submit Quote Request to ${SALES_EMAIL}`}
            </button>
          )}

          <p className="text-[11px] text-slate-400 text-center mt-2.5">
            By submitting, you agree to Solforbs reviewing your pharmacy details. We respect your privacy and never share your data.
          </p>
        </div>
      </form>
    </PublicShell>
  )
}
