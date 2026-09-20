import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Pause, Play, Plus, Send, Trash2, X } from 'lucide-react'
import { useState, type KeyboardEvent } from 'react'
import { CustomerPicker, useCustomer } from '../../components/CustomerPicker'
import { ProductSearch } from '../../components/ProductSearch'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { ConfirmDialog } from '../../components/ui/Modal'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { InlineError, NoAccess } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, Field, Input, Select } from '../../components/ui/primitives'
import { api, apiGet, apiPatch, apiPost, getApiError } from '../../lib/api'
import { formatDateTime, titleCase } from '../../lib/format'
import { useProduct, useProductCategories, useStores } from '../../lib/hooks'
import { usePermission } from '../../lib/permissions'
import { toast, toastApiError } from '../../lib/toast'
import type { Customer, Product, ReportDef } from '../../lib/types'

type Frequency = 'DAILY' | 'WEEKLY' | 'MONTHLY'

type ScheduledReport = {
  id: string
  report_key: string
  report_title: string
  filters_json: Record<string, string | number> | null
  frequency: Frequency
  run_at: string
  weekday: number | null
  month_day: number | null
  recipients: string[]
  is_active: boolean
  next_run_at: string | null
  last_run_at: string | null
  last_status: 'SENT' | 'FAILED' | null
  last_error: string | null
  creator?: { id: number; name: string } | null
}

type RunResult = { status: 'SENT' | 'FAILED'; error: string | null; rows: number | null; from: string; to: string; schedule: ScheduledReport }

const WEEKDAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday']
const NUMERIC_FILTERS = ['threshold_pct', 'threshold', 'dead_days', 'open_hour', 'close_hour']
const WINDOW_NOTE: Record<Frequency, string> = {
  DAILY: 'Each run covers the previous day.',
  WEEKLY: 'Each run covers the seven days up to yesterday.',
  MONTHLY: 'Each run covers the previous calendar month.',
}

function describeSchedule(s: Pick<ScheduledReport, 'frequency' | 'run_at' | 'weekday' | 'month_day'>) {
  if (s.frequency === 'WEEKLY') return `Weekly · ${WEEKDAYS[(s.weekday ?? 1) - 1]} ${s.run_at}`
  if (s.frequency === 'MONTHLY') return `Monthly · day ${s.month_day ?? 1} at ${s.run_at}`
  return `Daily at ${s.run_at}`
}

/** Part 20.3 — catalogue reports emailed as CSV on a schedule, run with the scheduler's own permissions. */
export default function ScheduledReportsPage() {
  const canSchedule = usePermission('report.schedule')
  const queryClient = useQueryClient()
  const [status, setStatus] = useState('')
  const [editing, setEditing] = useState<ScheduledReport | 'new' | null>(null)
  const [deleting, setDeleting] = useState<ScheduledReport | null>(null)

  const list = useQuery({
    queryKey: ['scheduled-reports'],
    queryFn: () => apiGet<{ data: ScheduledReport[] }>('/api/scheduled-reports'),
    enabled: canSchedule,
  })
  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['scheduled-reports'] })

  const runNow = useMutation({
    mutationFn: (s: ScheduledReport) => apiPost<RunResult>(`/api/scheduled-reports/${s.id}/run-now`),
    onSuccess: (r) => {
      if (r.status === 'SENT') toast.success(`${r.schedule.report_title} sent`, `${r.rows ?? 0} rows, ${r.from} to ${r.to}, to ${r.schedule.recipients.length} recipient(s).`)
      else toast.error('Run failed', r.error ?? undefined)
      invalidate()
    },
    onError: (e) => toastApiError(e, 'Run failed'),
  })
  const toggle = useMutation({
    mutationFn: (s: ScheduledReport) => apiPatch<ScheduledReport>(`/api/scheduled-reports/${s.id}`, { is_active: !s.is_active }),
    onSuccess: (s) => {
      toast.success(s.is_active ? 'Schedule activated' : 'Schedule paused')
      invalidate()
    },
    onError: (e) => toastApiError(e),
  })
  const remove = useMutation({
    mutationFn: (s: ScheduledReport) => api.delete(`/api/scheduled-reports/${s.id}`),
    onSuccess: () => {
      toast.success('Schedule deleted')
      setDeleting(null)
      invalidate()
    },
    onError: (e) => toastApiError(e),
  })

  if (!canSchedule) return <NoAccess permission="report.schedule" />

  const rows = (list.data?.data ?? []).filter((s) => !status || (status === 'ACTIVE' ? s.is_active : status === 'PAUSED' ? !s.is_active : s.last_status === 'FAILED'))

  const columns: Column<ScheduledReport>[] = [
    { key: 'report', header: 'Report', render: (s) => <><div className="font-semibold">{s.report_title}</div><div className="text-xs text-slate-500 font-mono">{s.report_key}</div></>, sortValue: (s) => s.report_title },
    { key: 'when', header: 'Schedule', render: (s) => describeSchedule(s) },
    { key: 'recipients', header: 'Recipients', render: (s) => <span title={s.recipients.join(', ')}>{s.recipients[0]}{s.recipients.length > 1 && <span className="text-slate-400 font-mono text-xs"> +{s.recipients.length - 1}</span>}</span> },
    { key: 'next', header: 'Next run', render: (s) => (s.is_active ? <span className="tabular">{formatDateTime(s.next_run_at)}</span> : <StatusBadge status="PAUSED" tone="slate" label="Paused" />), sortValue: (s) => s.next_run_at ?? '' },
    {
      key: 'last',
      header: 'Last run',
      render: (s) => (s.last_run_at ? <div className="flex items-center gap-2"><StatusBadge status={s.last_status === 'SENT' ? 'OK' : 'FAILED'} label={s.last_status ?? '—'} /><span className="tabular text-xs text-slate-500">{formatDateTime(s.last_run_at)}</span></div> : <span className="text-slate-400">Never</span>),
    },
    { key: 'owner', header: 'Runs as', render: (s) => s.creator?.name ?? '—' },
    {
      key: 'actions',
      header: '',
      align: 'right',
      render: (s) => (
        <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}>
          <Button size="sm" title="Run and email now" disabled={runNow.isPending} onClick={() => runNow.mutate(s)}><Send size={12} /> Run now</Button>
          <Button size="sm" variant="ghost" title={s.is_active ? 'Pause' : 'Activate'} disabled={toggle.isPending} onClick={() => toggle.mutate(s)}>{s.is_active ? <Pause size={12} /> : <Play size={12} />}</Button>
          <Button size="sm" variant="ghost" title="Delete" onClick={() => setDeleting(s)}><Trash2 size={12} /></Button>
        </div>
      ),
    },
  ]

  return (
    <Page>
      <PageHeader
        parent="Reports"
        title="Scheduled Reports"
        subtitle="Each schedule emails a CSV to its recipients and runs with the permissions of the person who set it up."
        actions={<Button variant="primary" onClick={() => setEditing('new')}><Plus size={13} /> New schedule</Button>}
      />
      <FilterBar>
        <Field label="Show">
          <Select value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="">All</option>
            <option value="ACTIVE">Active</option>
            <option value="PAUSED">Paused</option>
            <option value="FAILED">Last run failed</option>
          </Select>
        </Field>
      </FilterBar>
      <div className="ui-card">
        <DataTable
          columns={columns}
          rows={list.data ? rows : undefined}
          rowKey={(s) => s.id}
          isLoading={list.isLoading}
          error={list.error}
          onRetry={() => list.refetch()}
          onRowClick={(s) => setEditing(s)}
          emptyTitle="No scheduled reports"
          emptyHint="Schedule a catalogue report to have it emailed daily, weekly or monthly."
          renderExpanded={(s) => (s.last_error ? <div className="px-4 py-2 text-xs text-rose-600 font-medium">{s.last_error}</div> : <div className="px-4 py-2 text-xs text-slate-500">Recipients: {s.recipients.join(', ')}</div>)}
        />
      </div>

      <Drawer open={editing !== null} onClose={() => setEditing(null)} title={editing === 'new' ? 'New scheduled report' : 'Edit schedule'} subtitle={editing && editing !== 'new' ? editing.report_title : undefined} width={620}>
        {editing !== null && <ScheduleForm key={editing === 'new' ? 'new' : editing.id} schedule={editing === 'new' ? null : editing} onDone={() => { setEditing(null); invalidate() }} />}
      </Drawer>

      <ConfirmDialog
        open={!!deleting}
        title="Delete this schedule?"
        message={deleting ? `${deleting.report_title} will no longer be emailed to ${deleting.recipients.join(', ')}.` : undefined}
        confirmLabel="Delete"
        danger
        isPending={remove.isPending}
        onConfirm={() => deleting && remove.mutate(deleting)}
        onCancel={() => setDeleting(null)}
      />
    </Page>
  )
}

type FormState = {
  report_key: string
  frequency: Frequency
  run_at: string
  weekday: string
  month_day: string
  recipients: string[]
  is_active: boolean
  filters: Record<string, string>
}

function ScheduleForm({ schedule, onDone }: { schedule: ScheduledReport | null; onDone: () => void }) {
  const catalogue = useQuery({ queryKey: ['reports', 'catalogue'], queryFn: () => apiGet<{ data: ReportDef[] }>('/api/reports'), staleTime: 5 * 60_000 })
  const stores = useStores()
  const categories = useProductCategories()
  const initialFilters = Object.fromEntries(Object.entries(schedule?.filters_json ?? {}).map(([k, v]) => [k, String(v)]))
  const [form, setForm] = useState<FormState>({
    report_key: schedule?.report_key ?? '',
    frequency: schedule?.frequency ?? 'DAILY',
    run_at: schedule?.run_at ?? '07:00',
    weekday: String(schedule?.weekday ?? 1),
    month_day: String(schedule?.month_day ?? 1),
    recipients: schedule?.recipients ?? [],
    is_active: schedule?.is_active ?? true,
    filters: initialFilters,
  })
  const [emailDraft, setEmailDraft] = useState('')
  const [customer, setCustomer] = useState<Customer | null>(null)
  const [product, setProduct] = useState<Product | null>(null)
  const savedCustomer = useCustomer(!customer && form.filters.customer_id ? form.filters.customer_id : null)
  const savedProduct = useProduct(!product && form.filters.product_id ? form.filters.product_id : null)
  const set = (patch: Partial<FormState>) => setForm({ ...form, ...patch })
  const setFilter = (key: string, value: string) => setForm({ ...form, filters: { ...form.filters, [key]: value } })

  const defs = catalogue.data?.data ?? []
  const def = defs.find((d) => d.key === form.report_key)
  const extraFilters = (def?.filters ?? []).filter((f) => f !== 'from' && f !== 'to')
  const groups = [...new Set(defs.map((d) => d.group))]

  function addEmail(raw: string) {
    const emails = raw.split(/[\s,;]+/).map((e) => e.trim().toLowerCase()).filter(Boolean)
    const next = [...form.recipients]
    for (const e of emails) if (!next.includes(e)) next.push(e)
    set({ recipients: next })
    setEmailDraft('')
  }
  function onEmailKey(e: KeyboardEvent<HTMLInputElement>) {
    if (e.key === 'Enter' || e.key === ',' || e.key === ';') {
      e.preventDefault()
      if (emailDraft.trim()) addEmail(emailDraft)
    } else if (e.key === 'Backspace' && !emailDraft && form.recipients.length) {
      set({ recipients: form.recipients.slice(0, -1) })
    }
  }

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => {
      const recipients = emailDraft.trim() ? [...form.recipients, ...emailDraft.split(/[\s,;]+/).map((e) => e.trim().toLowerCase()).filter(Boolean)] : form.recipients
      const filters: Record<string, string | null> = {}
      for (const f of extraFilters) filters[f] = form.filters[f] ? form.filters[f] : null
      const body = {
        report_key: form.report_key,
        frequency: form.frequency,
        run_at: form.run_at,
        weekday: form.frequency === 'WEEKLY' ? Number(form.weekday) : null,
        month_day: form.frequency === 'MONTHLY' ? Number(form.month_day) : null,
        recipients,
        is_active: form.is_active,
        filters,
      }
      return schedule ? apiPatch<ScheduledReport>(`/api/scheduled-reports/${schedule.id}`, body) : apiPost<ScheduledReport>('/api/scheduled-reports', body)
    },
    onSuccess: (s) => {
      toast.success(schedule ? 'Schedule saved' : 'Schedule created', s.is_active ? `Next run ${formatDateTime(s.next_run_at)}` : 'Paused')
      onDone()
    },
  })
  const err = save.isError ? getApiError(save.error) : null
  const recipientError = err ? Object.entries(err.errors).find(([k]) => k.startsWith('recipients'))?.[1]?.[0] : undefined

  return (
    <div className="space-y-4">
      {schedule && (
        <DescriptionList
          items={[
            { label: 'Runs as', value: schedule.creator?.name ?? '—' },
            { label: 'Next run', value: schedule.is_active ? formatDateTime(schedule.next_run_at) : 'Paused' },
            { label: 'Last run', value: schedule.last_run_at ? `${schedule.last_status} · ${formatDateTime(schedule.last_run_at)}` : 'Never' },
            ...(schedule.last_error ? [{ label: 'Last error', value: <span className="text-rose-600 font-medium">{schedule.last_error}</span> }] : []),
          ]}
        />
      )}
      <Field label="Report" required error={err?.errors.report_key?.[0]} hint={def?.description}>
        <Select value={form.report_key} onChange={(e) => setForm({ ...form, report_key: e.target.value, filters: {} })} disabled={catalogue.isLoading}>
          <option value="">{catalogue.isLoading ? 'Loading reports…' : 'Choose a report…'}</option>
          {groups.map((g) => (
            <optgroup key={g} label={titleCase(g)}>
              {defs.filter((d) => d.group === g).map((d) => <option key={d.key} value={d.key}>{d.title}</option>)}
            </optgroup>
          ))}
        </Select>
      </Field>
      {catalogue.isError && <InlineError error={catalogue.error} />}

      {extraFilters.length > 0 && (
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          {extraFilters.includes('customer_id') && (
            <Field label="Customer" className="col-span-2" hint="Leave empty for every customer.">
              <CustomerPicker value={customer ?? savedCustomer.data ?? null} onChange={(c) => { setCustomer(c); setFilter('customer_id', c?.id ?? '') }} />
            </Field>
          )}
          {extraFilters.includes('product_id') && (
            <Field label="Product" className="col-span-2">
              {form.filters.product_id ? (
                <div className="ui-input flex items-center gap-2"><span className="flex-1 truncate">{product?.name ?? savedProduct.data?.name ?? 'Selected product'}</span><button type="button" aria-label="Clear product" onClick={() => { setProduct(null); setFilter('product_id', '') }} className="text-slate-400 hover:text-slate-600"><X size={12} /></button></div>
              ) : (
                <ProductSearch onSelect={(p) => { setProduct(p); setFilter('product_id', p.id) }} placeholder="Search product…" />
              )}
            </Field>
          )}
          {extraFilters.includes('store_id') && (
            <Field label="Store"><Select value={form.filters.store_id ?? ''} onChange={(e) => setFilter('store_id', e.target.value)}><option value="">All stores</option>{(stores.data ?? []).map((s) => <option key={s.id} value={s.id}>{s.code} · {s.name}</option>)}</Select></Field>
          )}
          {extraFilters.includes('category_id') && (
            <Field label="Category"><Select value={form.filters.category_id ?? ''} onChange={(e) => setFilter('category_id', e.target.value)}><option value="">All categories</option>{(categories.data ?? []).map((c) => <option key={c.id} value={c.id}>{c.code} · {c.name}</option>)}</Select></Field>
          )}
          {NUMERIC_FILTERS.filter((f) => extraFilters.includes(f)).map((f) => (
            <Field key={f} label={titleCase(f)} error={err?.errors[f]?.[0]}><Input inputMode="decimal" className="tabular" value={form.filters[f] ?? ''} onChange={(e) => setFilter(f, e.target.value.replace(/[^\d.]/g, ''))} /></Field>
          ))}
        </div>
      )}

      <div className="grid grid-cols-3 gap-3">
        <Field label="Frequency" required hint={WINDOW_NOTE[form.frequency]} className="col-span-3 sm:col-span-1">
          <Select value={form.frequency} onChange={(e) => set({ frequency: e.target.value as Frequency })}>
            <option value="DAILY">Daily</option>
            <option value="WEEKLY">Weekly</option>
            <option value="MONTHLY">Monthly</option>
          </Select>
        </Field>
        {form.frequency === 'WEEKLY' && (
          <Field label="Day" required error={err?.errors.weekday?.[0]}><Select value={form.weekday} onChange={(e) => set({ weekday: e.target.value })}>{WEEKDAYS.map((d, i) => <option key={d} value={i + 1}>{d}</option>)}</Select></Field>
        )}
        {form.frequency === 'MONTHLY' && (
          <Field label="Day of month" required hint="1–28, so every month has it." error={err?.errors.month_day?.[0]}><Select value={form.month_day} onChange={(e) => set({ month_day: e.target.value })}>{Array.from({ length: 28 }, (_, i) => <option key={i + 1} value={i + 1}>{i + 1}</option>)}</Select></Field>
        )}
        <Field label="Time" required error={err?.errors.run_at?.[0]}><Input type="time" step={900} value={form.run_at} onChange={(e) => set({ run_at: e.target.value.slice(0, 5) })} /></Field>
      </div>

      <Field label="Recipients" required error={recipientError} hint="Type an address and press Enter. Up to 20.">
        <div className="ui-input !h-auto min-h-8 flex flex-wrap items-center gap-1 py-1">
          {form.recipients.map((email) => (
            <span key={email} className="inline-flex items-center gap-1 rounded bg-slate-100 border border-slate-200 px-1.5 py-0.5 text-xs text-slate-700">
              {email}
              <button type="button" aria-label={`Remove ${email}`} className="text-slate-400 hover:text-slate-700" onClick={() => set({ recipients: form.recipients.filter((r) => r !== email) })}><X size={11} /></button>
            </span>
          ))}
          <input
            className="flex-1 min-w-[160px] bg-transparent outline-none text-xs"
            type="email"
            value={emailDraft}
            placeholder={form.recipients.length ? '' : 'name@company.co.ke'}
            onChange={(e) => setEmailDraft(e.target.value)}
            onKeyDown={onEmailKey}
            onBlur={() => emailDraft.trim() && addEmail(emailDraft)}
          />
        </div>
      </Field>

      <label className="flex items-center gap-2 text-xs text-slate-600 cursor-pointer"><input type="checkbox" checked={form.is_active} onChange={(e) => set({ is_active: e.target.checked })} /> Active</label>

      {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
      <div className="flex justify-end gap-2">
        <Button onClick={onDone}>Cancel</Button>
        <Button variant="primary" disabled={!form.report_key || (!form.recipients.length && !emailDraft.trim()) || save.isPending} onClick={() => save.mutate()}>
          {save.isPending ? 'Saving…' : schedule ? 'Save changes' : 'Create schedule'}
        </Button>
      </div>
    </div>
  )
}
