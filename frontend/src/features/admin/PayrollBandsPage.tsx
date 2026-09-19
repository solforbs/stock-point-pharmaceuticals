import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus } from 'lucide-react'
import { useState } from 'react'
import { Drawer } from '../../components/ui/Drawer'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { InlineError, NoAccess } from '../../components/ui/States'
import { Button, Field, Input, Select } from '../../components/ui/primitives'
import { apiDelete, apiGet, apiPatch, apiPost, getApiError } from '../../lib/api'
import { formatDate } from '../../lib/format'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'

type Band = {
  id: string
  band_type: string
  sequence: number
  effective_from: string
  effective_to: string | null
  lower: string
  upper: string | null
  rate_pct: string | null
  fixed_amount: string | null
  source: string | null
}

type Listing = { data: Band[]; band_types: string[] }

const TYPE_LABEL: Record<string, string> = {
  PAYE: 'PAYE brackets',
  PAYE_RELIEF: 'PAYE personal relief',
  NSSF: 'NSSF tiers',
  SHIF: 'SHIF',
  HOUSING_LEVY: 'Housing levy',
  PENSION_RELIEF_CAP: 'Pension relief cap',
}

type Form = {
  band_type: string
  sequence: string
  effective_from: string
  effective_to: string
  lower: string
  upper: string
  rate_pct: string
  fixed_amount: string
  source: string
}

const empty: Form = { band_type: 'PAYE', sequence: '1', effective_from: '', effective_to: '', lower: '0', upper: '', rate_pct: '', fixed_amount: '', source: '' }

const amount = (v: string | null) => (v === null ? '—' : Number(v).toLocaleString('en-KE', { maximumFractionDigits: 2 }))

/**
 * Part 15 — the statutory rates payroll calculates from. They change by law,
 * so the System Administrator keeps them here. A processed payroll keeps a
 * snapshot of the rates it used, so editing a band never rewrites one.
 */
export default function PayrollBandsPage() {
  const canManage = usePermission('admin.settings')
  const queryClient = useQueryClient()
  const [editing, setEditing] = useState<Band | 'new' | null>(null)
  const [form, setForm] = useState<Form>(empty)
  const set = (patch: Partial<Form>) => setForm({ ...form, ...patch })

  const listing = useQuery({ queryKey: ['payroll-bands'], queryFn: () => apiGet<Listing>('/api/payroll-bands'), enabled: canManage })
  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['payroll-bands'] })

  const payload = () => ({
    band_type: form.band_type,
    sequence: Number(form.sequence),
    effective_from: form.effective_from,
    effective_to: form.effective_to || null,
    lower: form.lower === '' ? 0 : Number(form.lower),
    upper: form.upper === '' ? null : Number(form.upper),
    rate_pct: form.rate_pct === '' ? null : Number(form.rate_pct),
    fixed_amount: form.fixed_amount === '' ? null : Number(form.fixed_amount),
    source: form.source || null,
  })

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => (editing === 'new' ? apiPost('/api/payroll-bands', payload()) : apiPatch(`/api/payroll-bands/${(editing as Band).id}`, payload())),
    onSuccess: () => {
      toast.success(editing === 'new' ? 'Band added' : 'Band updated')
      setEditing(null)
      invalidate()
    },
  })

  const remove = useMutation({
    meta: { silent: true },
    mutationFn: (id: string) => apiDelete(`/api/payroll-bands/${id}`),
    onSuccess: () => {
      toast.success('Band deleted')
      setEditing(null)
      invalidate()
    },
    onError: (e) => toast.error(getApiError(e).message),
  })

  if (!canManage) {
    return <Page><PageHeader parent="Admin" title="Payroll Bands" /><div className="ui-card"><NoAccess permission="admin.settings" /></div></Page>
  }

  const openNew = () => { setForm(empty); setEditing('new') }
  const openEdit = (b: Band) => {
    setForm({
      band_type: b.band_type, sequence: String(b.sequence), effective_from: b.effective_from?.slice(0, 10) ?? '',
      effective_to: b.effective_to?.slice(0, 10) ?? '', lower: b.lower ?? '0', upper: b.upper ?? '',
      rate_pct: b.rate_pct ?? '', fixed_amount: b.fixed_amount ?? '', source: b.source ?? '',
    })
    setEditing(b)
  }

  const err = save.isError ? getApiError(save.error) : null
  const grouped = (listing.data?.band_types ?? []).map((type) => ({ type, bands: (listing.data?.data ?? []).filter((b) => b.band_type === type) }))

  return (
    <Page>
      <PageHeader
        parent="Admin"
        title="Payroll Bands"
        subtitle="The statutory rates payroll is calculated from. For a change on a date, end the old band the day before and add the new one."
        actions={<Button variant="primary" onClick={openNew}><Plus size={13} /> Add band</Button>}
      />

      {listing.error && <InlineError error={listing.error} />}

      <div className="space-y-4">
        {grouped.map(({ type, bands }) => (
          <div key={type} className="ui-card overflow-hidden">
            <div className="px-4 py-2.5 border-b border-slate-100 flex items-center justify-between">
              <h3 className="text-sm font-semibold text-slate-900">{TYPE_LABEL[type] ?? type}</h3>
              <span className="text-xs text-slate-500 font-mono">{bands.length} band(s)</span>
            </div>
            {bands.length === 0 ? (
              <p className="px-4 py-3 text-xs text-amber-700">No bands — payroll will not deduct {TYPE_LABEL[type] ?? type}.</p>
            ) : (
              <table className="w-full text-sm">
                <thead className="bg-slate-50 text-xs uppercase text-slate-500 tracking-wide">
                  <tr>
                    <th className="text-left px-4 py-1.5">#</th>
                    <th className="text-right px-4 py-1.5">From (KES)</th>
                    <th className="text-right px-4 py-1.5">To (KES)</th>
                    <th className="text-right px-4 py-1.5">Rate</th>
                    <th className="text-right px-4 py-1.5">Fixed</th>
                    <th className="text-left px-4 py-1.5">In force</th>
                    <th className="text-left px-4 py-1.5">Source</th>
                  </tr>
                </thead>
                <tbody>
                  {bands.map((b) => (
                    <tr key={b.id} onClick={() => openEdit(b)} className="border-t border-slate-50 hover:bg-slate-50/70 cursor-pointer">
                      <td className="px-4 py-2 tabular">{b.sequence}</td>
                      <td className="px-4 py-2 text-right tabular">{amount(b.lower)}</td>
                      <td className="px-4 py-2 text-right tabular">{b.upper === null ? 'and above' : amount(b.upper)}</td>
                      <td className="px-4 py-2 text-right tabular">{b.rate_pct === null ? '—' : `${Number(b.rate_pct)}%`}</td>
                      <td className="px-4 py-2 text-right tabular">{amount(b.fixed_amount)}</td>
                      <td className="px-4 py-2 whitespace-nowrap">{formatDate(b.effective_from)} – {b.effective_to ? formatDate(b.effective_to) : 'open'}</td>
                      <td className="px-4 py-2 text-xs text-slate-500 font-mono">{b.source}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </div>
        ))}
      </div>

      <Drawer
        open={editing !== null}
        onClose={() => setEditing(null)}
        title={editing === 'new' ? 'Add payroll band' : 'Edit payroll band'}
        width={520}
        actions={editing !== null && editing !== 'new' ? (
          <Button size="sm" variant="danger" disabled={remove.isPending} onClick={() => remove.mutate((editing as Band).id)}>Delete</Button>
        ) : null}
      >
        <div className="space-y-3">
          <div className="grid grid-cols-2 gap-3">
            <Field label="Type" required error={err?.errors.band_type?.[0]}>
              <Select value={form.band_type} onChange={(e) => set({ band_type: e.target.value })}>
                {(listing.data?.band_types ?? []).map((t) => (<option key={t} value={t}>{TYPE_LABEL[t] ?? t}</option>))}
              </Select>
            </Field>
            <Field label="Order" required hint="Brackets apply in this order." error={err?.errors.sequence?.[0]}>
              <Input inputMode="numeric" value={form.sequence} onChange={(e) => set({ sequence: e.target.value.replace(/\D/g, '') })} />
            </Field>
            <Field label="From (KES)" required error={err?.errors.lower?.[0]}>
              <Input inputMode="decimal" value={form.lower} onChange={(e) => set({ lower: e.target.value })} />
            </Field>
            <Field label="To (KES)" hint="Empty means no upper limit." error={err?.errors.upper?.[0]}>
              <Input inputMode="decimal" value={form.upper} onChange={(e) => set({ upper: e.target.value })} />
            </Field>
            <Field label="Rate %" error={err?.errors.rate_pct?.[0]}>
              <Input inputMode="decimal" value={form.rate_pct} onChange={(e) => set({ rate_pct: e.target.value })} />
            </Field>
            <Field label="Fixed amount (KES)" hint="For relief and caps." error={err?.errors.fixed_amount?.[0]}>
              <Input inputMode="decimal" value={form.fixed_amount} onChange={(e) => set({ fixed_amount: e.target.value })} />
            </Field>
            <Field label="In force from" required error={err?.errors.effective_from?.[0]}>
              <Input type="date" value={form.effective_from} onChange={(e) => set({ effective_from: e.target.value })} />
            </Field>
            <Field label="Until" hint="Empty means still in force." error={err?.errors.effective_to?.[0]}>
              <Input type="date" value={form.effective_to} onChange={(e) => set({ effective_to: e.target.value })} />
            </Field>
          </div>
          <Field label="Legal source" hint="The Act or notice this rate comes from.">
            <Input value={form.source} maxLength={255} onChange={(e) => set({ source: e.target.value })} placeholder="Finance Act 2025 s.5" />
          </Field>
          {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
          <div className="flex justify-end gap-2 pt-2">
            <Button onClick={() => setEditing(null)}>Cancel</Button>
            <Button variant="primary" disabled={!form.effective_from || save.isPending} onClick={() => save.mutate()}>
              {save.isPending ? 'Saving…' : 'Save band'}
            </Button>
          </div>
        </div>
      </Drawer>
    </Page>
  )
}
