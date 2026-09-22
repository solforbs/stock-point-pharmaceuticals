import { useMutation, useQuery } from '@tanstack/react-query'
import { CheckCircle2 } from 'lucide-react'
import { useState } from 'react'
import { Button, Field, Input, Select, Textarea } from '../../components/ui/primitives'
import { InlineError } from '../../components/ui/States'
import { apiGet, api, ensureCsrfCookie, getApiError } from '../../lib/api'
import { formatMoney } from '../../lib/money'
import type { Plan } from '../../lib/types'
import { PublicShell } from './PublicShell'

type Form = { institution_name: string; contact_name: string; email: string; phone: string; town: string; branches_count: string; users_count: string; plan_id: string; message: string }

const EMPTY: Form = { institution_name: '', contact_name: '', email: '', phone: '', town: '', branches_count: '', users_count: '', plan_id: '', message: '' }

/** An institution asks for a quote; the platform reviews it and emails a registration link. */
export default function RequestQuotePage() {
  const [form, setForm] = useState<Form>(EMPTY)
  const set = (patch: Partial<Form>) => setForm((f) => ({ ...f, ...patch }))
  const plans = useQuery({ queryKey: ['public', 'plans'], queryFn: () => apiGet<Plan[]>('/api/public/plans') })

  const submit = useMutation({
    meta: { silent: true },
    mutationFn: async () => {
      await ensureCsrfCookie()
      await api.post('/api/public/quote-requests', {
        ...form,
        phone: form.phone || null,
        town: form.town || null,
        branches_count: form.branches_count ? Number(form.branches_count) : null,
        users_count: form.users_count ? Number(form.users_count) : null,
        plan_id: form.plan_id || null,
        message: form.message || null,
      })
    },
  })
  const err = submit.isError ? getApiError(submit.error) : null

  if (submit.isSuccess) {
    return (
      <PublicShell title="Request received">
        <div className="flex flex-col items-center text-center gap-3 py-4">
          <CheckCircle2 className="w-12 h-12 text-emerald-600" />
          <p className="text-sm text-slate-600">
            Thank you. We have emailed <strong>{form.email}</strong> to confirm. Once your request is approved you will receive a link to set up{' '}
            <strong>{form.institution_name}</strong> and start a free 7-day trial.
          </p>
        </div>
      </PublicShell>
    )
  }

  const valid = form.institution_name.trim() && form.contact_name.trim() && /.+@.+\..+/.test(form.email)

  return (
    <PublicShell wide title="Request a quote" subtitle="Tell us about your pharmacy or institution. We review every request and reply by email.">
      <form
        className="grid grid-cols-1 sm:grid-cols-2 gap-3"
        onSubmit={(e) => {
          e.preventDefault()
          if (valid) submit.mutate()
        }}
      >
        <Field label="Institution name" required className="sm:col-span-2" error={err?.errors.institution_name?.[0]}>
          <Input value={form.institution_name} onChange={(e) => set({ institution_name: e.target.value })} placeholder="e.g. Lodwar Community Chemist" />
        </Field>
        <Field label="Your name" required error={err?.errors.contact_name?.[0]}>
          <Input value={form.contact_name} onChange={(e) => set({ contact_name: e.target.value })} />
        </Field>
        <Field label="Email" required error={err?.errors.email?.[0]}>
          <Input type="email" value={form.email} onChange={(e) => set({ email: e.target.value })} />
        </Field>
        <Field label="Phone">
          <Input value={form.phone} onChange={(e) => set({ phone: e.target.value })} placeholder="07…" />
        </Field>
        <Field label="Town">
          <Input value={form.town} onChange={(e) => set({ town: e.target.value })} />
        </Field>
        <Field label="Branches">
          <Input inputMode="numeric" value={form.branches_count} onChange={(e) => set({ branches_count: e.target.value.replace(/\D/g, '') })} />
        </Field>
        <Field label="Staff who will sign in">
          <Input inputMode="numeric" value={form.users_count} onChange={(e) => set({ users_count: e.target.value.replace(/\D/g, '') })} />
        </Field>
        <Field label="Plan you are interested in" className="sm:col-span-2">
          <Select value={form.plan_id} onChange={(e) => set({ plan_id: e.target.value })}>
            <option value="">Not sure yet</option>
            {(plans.data ?? []).map((p) => (
              <option key={p.id} value={p.id}>
                {p.name} — {p.currency} {formatMoney(p.price_monthly)} / month
              </option>
            ))}
          </Select>
        </Field>
        <Field label="Anything else we should know" className="sm:col-span-2">
          <Textarea rows={3} value={form.message} onChange={(e) => set({ message: e.target.value })} />
        </Field>
        {err && Object.keys(err.errors).length === 0 && <InlineError error={submit.error} className="sm:col-span-2" />}
        <Button type="submit" variant="primary" size="lg" className="sm:col-span-2" disabled={!valid || submit.isPending}>
          {submit.isPending ? 'Sending…' : 'Send request'}
        </Button>
      </form>
    </PublicShell>
  )
}
