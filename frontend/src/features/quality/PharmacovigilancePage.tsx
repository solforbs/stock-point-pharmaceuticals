import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { X } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { CustomerPicker } from '../../components/CustomerPicker'
import { ProductSearch, useDebounced } from '../../components/ProductSearch'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { ConfirmDialog } from '../../components/ui/Modal'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton, NoAccess } from '../../components/ui/States'
import { StatusBadge, type StatusTone } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, Field, Input, Select, Textarea } from '../../components/ui/primitives'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import { apiGet, apiPatch, apiPost, getApiError } from '../../lib/api'
import { formatDate, formatDateTime, todayIso, titleCase } from '../../lib/format'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Customer, NamedRef, Paginated, ProductBatch } from '../../lib/types'

type Seriousness = 'NON_SERIOUS' | 'SERIOUS' | 'LIFE_THREATENING' | 'FATAL'
type AdrStatus = 'DRAFT' | 'SUBMITTED' | 'CLOSED'

type AdrReport = {
  id: string
  doc_number: string
  product_id: string
  batch_id: string | null
  customer_id: string | null
  patient_initials: string
  patient_age: number | null
  patient_sex: 'M' | 'F' | 'U' | null
  reaction_description: string
  onset_date: string
  seriousness: Seriousness
  outcome: string
  action_taken: string | null
  reporter_name: string
  investigation_notes: string | null
  status: AdrStatus
  ppb_reference: string | null
  submitted_at: string | null
  closed_at: string | null
  created_at: string
  product?: NamedRef | null
  batch?: { id: string; batch_number: string; expiry_date: string; status: string } | null
  customer?: NamedRef | null
  creator?: { id: number; name: string } | null
  submitter?: { id: number; name: string } | null
  closer?: { id: number; name: string } | null
}

const SERIOUSNESS: Seriousness[] = ['NON_SERIOUS', 'SERIOUS', 'LIFE_THREATENING', 'FATAL']
const OUTCOMES = ['RECOVERED', 'RECOVERING', 'NOT_RECOVERED', 'UNKNOWN', 'FATAL'] as const
const SERIOUS_TONE: Record<Seriousness, StatusTone> = { NON_SERIOUS: 'slate', SERIOUS: 'amber', LIFE_THREATENING: 'red', FATAL: 'red' }
const STATUS_TONE: Record<AdrStatus, StatusTone> = { DRAFT: 'slate', SUBMITTED: 'blue', CLOSED: 'green' }

/** Part 11.4 — adverse drug reaction reports, linked to the batch so a pattern can be seen. */
export default function PharmacovigilancePage() {
  const canReport = usePermission('adr.report')
  const canManage = usePermission('adr.manage')
  const [params, setParams] = useSearchParams()
  const selectedId = params.get('report')
  const [status, setStatus] = useState('')
  const [seriousness, setSeriousness] = useState('')
  const [product, setProduct] = useState<NamedRef | null>(null)
  const [q, setQ] = useState('')
  const dq = useDebounced(q, 250)
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)

  const list = useQuery({
    queryKey: ['adr-reports', 'list', status, seriousness, product?.id, dq, page],
    queryFn: () => apiGet<Paginated<AdrReport>>('/api/adr-reports', { status, seriousness, product_id: product?.id, q: dq, page }),
    enabled: canReport || canManage,
    placeholderData: (prev) => prev,
  })

  if (!canReport && !canManage) {
    return (
      <Page>
        <PageHeader parent="Quality & Compliance" title="Pharmacovigilance" />
        <div className="ui-card"><NoAccess permission="adr.report" /></div>
      </Page>
    )
  }

  const columns: Column<AdrReport>[] = [
    { key: 'doc', header: 'Report', render: (r) => <span className="font-semibold font-mono tabular">{r.doc_number}</span>, sortValue: (r) => r.doc_number },
    { key: 'onset', header: 'Onset', render: (r) => <span className="tabular font-mono text-xs">{formatDate(r.onset_date)}</span>, sortValue: (r) => r.onset_date },
    { key: 'product', header: 'Product', render: (r) => <>{r.product?.name ?? '—'}{r.batch && <div className="text-xs text-slate-500 font-mono">Batch {r.batch.batch_number}</div>}</>, sortValue: (r) => r.product?.name ?? '' },
    { key: 'patient', header: 'Patient', render: (r) => <span className="tabular">{r.patient_initials}{r.patient_age !== null ? ` · ${r.patient_age}y` : ''}{r.patient_sex ? ` · ${r.patient_sex}` : ''}</span> },
    { key: 'seriousness', header: 'Seriousness', render: (r) => <StatusBadge status={r.seriousness} tone={SERIOUS_TONE[r.seriousness]} />, sortValue: (r) => SERIOUSNESS.indexOf(r.seriousness) },
    { key: 'outcome', header: 'Outcome', render: (r) => titleCase(r.outcome) },
    { key: 'ppb', header: 'PPB reference', render: (r) => r.ppb_reference ?? <span className="text-slate-400">—</span> },
    { key: 'status', header: 'Status', render: (r) => <StatusBadge status={r.status} tone={STATUS_TONE[r.status]} /> },
  ]

  return (
    <Page>
      <PageHeader
        parent="Quality & Compliance"
        title="Pharmacovigilance"
        subtitle="Adverse drug reactions, reported to the PPB (PViMS). Linking a report to its batch is what turns a complaint into a detectable pattern (Part 11.4)."
        actions={
          canReport ? (
            <div id="tour-adr-new">
              <Button variant="primary" onClick={() => setCreating(true)}>New ADR report</Button>
            </div>
          ) : null
        }
      />
      <div id="tour-adr-filters">
        <FilterBar>
          <Field label="Search" className="w-56"><Input placeholder="Report no., PPB ref, initials" value={q} onChange={(e) => { setQ(e.target.value); setPage(1) }} /></Field>
          <Field label="Status" className="w-40">
            <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
              <option value="">All</option>
              {(['DRAFT', 'SUBMITTED', 'CLOSED'] as const).map((s) => (<option key={s} value={s}>{titleCase(s)}</option>))}
            </Select>
          </Field>
          <Field label="Seriousness" className="w-44">
            <Select value={seriousness} onChange={(e) => { setSeriousness(e.target.value); setPage(1) }}>
              <option value="">All</option>
              {SERIOUSNESS.map((s) => (<option key={s} value={s}>{titleCase(s)}</option>))}
            </Select>
          </Field>
          <Field label="Product" className="w-72">
            {product ? (
              <div className="ui-input flex items-center gap-2">
                <span className="flex-1 truncate">{product.name}</span>
                <button type="button" aria-label="Clear product" onClick={() => { setProduct(null); setPage(1) }} className="text-slate-400 hover:text-slate-600"><X size={13} /></button>
              </div>
            ) : (
              <ProductSearch placeholder="Filter by product…" onSelect={(p) => { setProduct({ id: p.id, code: p.code, name: p.name }); setPage(1) }} />
            )}
          </Field>
        </FilterBar>
      </div>
      <div id="tour-adr-table" className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(r) => r.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(r) => setParams({ report: r.id })} selectedKey={selectedId} emptyTitle="No ADR reports" emptyHint="Record any suspected adverse reaction a patient or customer reports, even if the link to the medicine is uncertain." />
        <Pagination page={list.data} onPage={setPage} />
      </div>
      <ReportDrawer id={selectedId} onClose={() => setParams({})} />
      <Drawer open={creating} onClose={() => setCreating(false)} title="New ADR report" width={720}>
        {creating && <AdrForm onDone={(r) => { setCreating(false); setParams({ report: r.id }) }} onCancel={() => setCreating(false)} />}
      </Drawer>
    </Page>
  )
}

function ReportDrawer({ id, onClose }: { id: string | null; onClose: () => void }) {
  const queryClient = useQueryClient()
  const canReport = usePermission('adr.report')
  const canManage = usePermission('adr.manage')
  const [editing, setEditing] = useState(false)
  const [confirm, setConfirm] = useState<'submit' | 'close' | null>(null)
  const [ppb, setPpb] = useState('')
  const [notes, setNotes] = useState('')
  const detail = useQuery({ queryKey: ['adr-reports', 'detail', id], queryFn: () => apiGet<AdrReport>(`/api/adr-reports/${id}`), enabled: !!id })
  const r = detail.data

  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ['adr-reports'] })
    queryClient.invalidateQueries({ queryKey: ['dashboard'] })
  }
  const submit = useMutation({
    mutationFn: () => apiPost<AdrReport>(`/api/adr-reports/${id}/submit`, { ppb_reference: ppb || null }),
    onSuccess: (x) => { toast.success(`${x.doc_number} submitted`); setConfirm(null); refresh() },
  })
  const close = useMutation({
    mutationFn: () => apiPost<AdrReport>(`/api/adr-reports/${id}/close`, { ppb_reference: ppb || null, investigation_notes: notes || null }),
    onSuccess: (x) => { toast.success(`${x.doc_number} closed`); setConfirm(null); refresh() },
  })

  const openAction = (kind: 'submit' | 'close') => {
    setPpb(r?.ppb_reference ?? '')
    setNotes(r?.investigation_notes ?? '')
    setConfirm(kind)
  }
  const serious = r ? r.seriousness !== 'NON_SERIOUS' : false

  return (
    <Drawer open={!!id} onClose={() => { setEditing(false); onClose() }} title={r?.doc_number ?? 'ADR report'} subtitle={r ? `${r.product?.name ?? ''} · reported ${formatDateTime(r.created_at)}` : undefined} width={editing ? 720 : 600}>
      {detail.isLoading && <LoadingSkeleton rows={8} />}
      {detail.error && <InlineError error={detail.error} />}
      {r && editing && <AdrForm report={r} onDone={() => setEditing(false)} onCancel={() => setEditing(false)} />}
      {r && !editing && (
        <div className="space-y-4">
          <div className="flex items-center justify-between gap-2">
            <div className="flex items-center gap-2">
              <StatusBadge status={r.status} tone={STATUS_TONE[r.status]} />
              <StatusBadge status={r.seriousness} tone={SERIOUS_TONE[r.seriousness]} />
            </div>
            <div className="flex gap-2">
              {r.status === 'DRAFT' && canReport && <Button onClick={() => setEditing(true)}>Edit</Button>}
              {r.status === 'DRAFT' && canReport && <Button variant="primary" onClick={() => openAction('submit')}>Submit</Button>}
              {r.status === 'SUBMITTED' && canManage && <Button variant="primary" onClick={() => openAction('close')}>Close report</Button>}
            </div>
          </div>
          {serious && r.status !== 'CLOSED' && (
            <div className="text-xs rounded-lg px-3.5 py-2.5 border border-rose-200 bg-rose-50 text-rose-800 font-medium leading-relaxed">
              Serious reaction: submitting flags it for the Director, and it cannot be closed until the PPB PViMS reference is recorded.
            </div>
          )}
          <DescriptionList
            items={[
              { label: 'Product', value: `${r.product?.code ?? ''} ${r.product?.name ?? ''}` },
              { label: 'Batch', value: r.batch ? `${r.batch.batch_number} · expires ${formatDate(r.batch.expiry_date)} · ${titleCase(r.batch.status)}` : '—' },
              { label: 'Customer', value: r.customer?.name ?? '—' },
              { label: 'Patient', value: `${r.patient_initials}${r.patient_age !== null ? `, ${r.patient_age} years` : ''}${r.patient_sex ? `, ${r.patient_sex === 'M' ? 'male' : r.patient_sex === 'F' ? 'female' : 'sex unknown'}` : ''}` },
              { label: 'Onset', value: formatDate(r.onset_date) },
              { label: 'Reaction', value: <span className="whitespace-pre-wrap">{r.reaction_description}</span> },
              { label: 'Outcome', value: titleCase(r.outcome) },
              { label: 'Action taken', value: r.action_taken ?? '—' },
              { label: 'Reporter', value: r.reporter_name },
              { label: 'PPB reference', value: r.ppb_reference ?? '—' },
              { label: 'Investigation', value: r.investigation_notes ? <span className="whitespace-pre-wrap">{r.investigation_notes}</span> : '—' },
              { label: 'Submitted', value: r.submitted_at ? `${r.submitter?.name ?? '—'} · ${formatDateTime(r.submitted_at)}` : '—' },
              { label: 'Closed', value: r.closed_at ? `${r.closer?.name ?? '—'} · ${formatDateTime(r.closed_at)}` : '—' },
            ]}
          />
          {r.status === 'SUBMITTED' && canManage && <PpbDetails key={`${r.id}-${r.ppb_reference ?? ''}`} report={r} onSaved={refresh} />}
        </div>
      )}
      <ConfirmDialog
        open={confirm !== null}
        title={confirm === 'submit' ? `Submit ${r?.doc_number ?? ''}` : `Close ${r?.doc_number ?? ''}`}
        message={
          <span className="block space-y-3">
            <span className="block">{confirm === 'submit' ? 'The report is locked once submitted; only the PPB reference and investigation notes can be added afterwards.' : 'Closing records that follow-up is complete.'}</span>
            <Field label="PPB PViMS reference" hint={confirm === 'close' && serious ? 'Required to close a serious report.' : 'Optional — can be added later.'}>
              <Input value={ppb} onChange={(e) => setPpb(e.target.value)} />
            </Field>
            {confirm === 'close' && <Field label="Investigation notes"><Textarea rows={3} value={notes} onChange={(e) => setNotes(e.target.value)} /></Field>}
            {(submit.isError || close.isError) && <InlineError error={confirm === 'submit' ? submit.error : close.error} />}
          </span>
        }
        confirmLabel={confirm === 'submit' ? 'Submit report' : 'Close report'}
        isPending={submit.isPending || close.isPending}
        onCancel={() => setConfirm(null)}
        onConfirm={() => (confirm === 'submit' ? submit.mutate() : close.mutate())}
      />
    </Drawer>
  )
}

/** Once submitted, only the PPB reference and investigation notes change (adr.manage). */
function PpbDetails({ report, onSaved }: { report: AdrReport; onSaved: () => void }) {
  const [ppb, setPpb] = useState(report.ppb_reference ?? '')
  const [notes, setNotes] = useState(report.investigation_notes ?? '')
  const save = useMutation({
    mutationFn: () => apiPatch<AdrReport>(`/api/adr-reports/${report.id}`, { ppb_reference: ppb || null, investigation_notes: notes || null }),
    onSuccess: () => { toast.success('PPB details saved'); onSaved() },
  })
  const dirty = ppb !== (report.ppb_reference ?? '') || notes !== (report.investigation_notes ?? '')

  return (
    <div className="border-t border-slate-200 pt-4 space-y-3">
      <div className="text-sm font-semibold text-slate-900">PPB reporting</div>
      <Field label="PPB PViMS reference"><Input value={ppb} onChange={(e) => setPpb(e.target.value)} /></Field>
      <Field label="Investigation notes"><Textarea rows={3} value={notes} onChange={(e) => setNotes(e.target.value)} /></Field>
      {save.isError && <InlineError error={save.error} />}
      <div className="flex justify-end"><Button disabled={save.isPending || !dirty} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : 'Save PPB details'}</Button></div>
    </div>
  )
}

type AdrFormState = {
  product: NamedRef | null
  batch_id: string
  customer: Customer | null
  patient_initials: string
  patient_age: string
  patient_sex: string
  reaction_description: string
  onset_date: string
  seriousness: Seriousness
  outcome: string
  action_taken: string
  reporter_name: string
}

function AdrForm({ report, onDone, onCancel }: { report?: AdrReport; onDone: (r: AdrReport) => void; onCancel: () => void }) {
  const queryClient = useQueryClient()
  const { data: me } = useCurrentUser()
  const canViewStock = usePermission('stock.view')
  const [form, setForm] = useState<AdrFormState>({
    product: report?.product ?? null,
    batch_id: report?.batch_id ?? '',
    customer: report?.customer ? ({ ...report.customer, code: report.customer.code ?? '' } as Customer) : null,
    patient_initials: report?.patient_initials ?? '',
    patient_age: report?.patient_age !== null && report?.patient_age !== undefined ? String(report.patient_age) : '',
    patient_sex: report?.patient_sex ?? '',
    reaction_description: report?.reaction_description ?? '',
    onset_date: report?.onset_date?.slice(0, 10) ?? todayIso(),
    seriousness: report?.seriousness ?? 'NON_SERIOUS',
    outcome: report?.outcome ?? 'UNKNOWN',
    action_taken: report?.action_taken ?? '',
    reporter_name: report?.reporter_name ?? me?.name ?? '',
  })
  const set = (patch: Partial<AdrFormState>) => setForm({ ...form, ...patch })

  const batches = useQuery({
    queryKey: ['batches', 'for-product', form.product?.id],
    queryFn: () => apiGet<Paginated<ProductBatch>>('/api/batches', { product_id: form.product?.id, per_page: 200 }),
    enabled: !!form.product && canViewStock,
  })

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => {
      const body = {
        product_id: form.product?.id,
        batch_id: form.batch_id || null,
        customer_id: form.customer?.id ?? null,
        patient_initials: form.patient_initials,
        patient_age: form.patient_age === '' ? null : Number(form.patient_age),
        patient_sex: form.patient_sex || null,
        reaction_description: form.reaction_description,
        onset_date: form.onset_date,
        seriousness: form.seriousness,
        outcome: form.outcome,
        action_taken: form.action_taken || null,
        reporter_name: form.reporter_name,
      }
      return report ? apiPatch<AdrReport>(`/api/adr-reports/${report.id}`, body) : apiPost<AdrReport>('/api/adr-reports', body)
    },
    onSuccess: (r) => {
      toast.success(report ? `${r.doc_number} updated` : `${r.doc_number} saved as draft`)
      queryClient.invalidateQueries({ queryKey: ['adr-reports'] })
      onDone(r)
    },
  })
  const err = save.isError ? getApiError(save.error) : null
  const valid = !!form.product && form.patient_initials.trim() && form.reaction_description.trim().length >= 5 && form.onset_date && form.reporter_name.trim()

  return (
    <div className="space-y-4">
      <Field label="Suspected product" required error={err?.errors.product_id?.[0]}>
        {form.product ? (
          <div className="ui-input flex items-center gap-2">
            <span className="flex-1 truncate font-semibold">{form.product.name}</span>
            <button type="button" aria-label="Change product" onClick={() => set({ product: null, batch_id: '' })} className="text-slate-400 hover:text-slate-600"><X size={13} /></button>
          </div>
        ) : (
          <ProductSearch autoFocus onSelect={(p) => set({ product: { id: p.id, code: p.code, name: p.name }, batch_id: '' })} />
        )}
      </Field>
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Field label="Batch" hint={!canViewStock ? 'Batch list needs stock view permission.' : 'Links the report to a batch so repeat reports are detected.'} error={err?.errors.batch_id?.[0]}>
          <Select value={form.batch_id} disabled={!form.product || !canViewStock} onChange={(e) => set({ batch_id: e.target.value })}>
            <option value="">{batches.isLoading ? 'Loading…' : 'Unknown / not recorded'}</option>
            {batches.data?.data.map((b) => (<option key={b.id} value={b.id}>{b.batch_number} · exp {formatDate(b.expiry_date)} · {titleCase(b.status)}</option>))}
          </Select>
        </Field>
        <Field label="Customer (if supplied through one)">
          <CustomerPicker value={form.customer} onChange={(c) => set({ customer: c })} />
        </Field>
        <Field label="Patient initials" required hint="Anonymised — never the full name." error={err?.errors.patient_initials?.[0]}><Input maxLength={10} value={form.patient_initials} onChange={(e) => set({ patient_initials: e.target.value.toUpperCase() })} /></Field>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <Field label="Age" error={err?.errors.patient_age?.[0]}><Input inputMode="numeric" className="tabular" value={form.patient_age} onChange={(e) => set({ patient_age: e.target.value.replace(/\D/g, '').slice(0, 3) })} /></Field>
          <Field label="Sex">
            <Select value={form.patient_sex} onChange={(e) => set({ patient_sex: e.target.value })}>
              <option value="">—</option><option value="F">Female</option><option value="M">Male</option><option value="U">Unknown</option>
            </Select>
          </Field>
        </div>
      </div>
      <Field label="Reaction description" required error={err?.errors.reaction_description?.[0]}><Textarea rows={4} value={form.reaction_description} onChange={(e) => set({ reaction_description: e.target.value })} /></Field>
      <div className="grid grid-cols-3 gap-3">
        <Field label="Onset date" required error={err?.errors.onset_date?.[0]}><Input type="date" max={todayIso()} value={form.onset_date} onChange={(e) => set({ onset_date: e.target.value })} /></Field>
        <Field label="Seriousness" required>
          <Select value={form.seriousness} onChange={(e) => set({ seriousness: e.target.value as Seriousness })}>
            {SERIOUSNESS.map((s) => (<option key={s} value={s}>{titleCase(s)}</option>))}
          </Select>
        </Field>
        <Field label="Outcome" required>
          <Select value={form.outcome} onChange={(e) => set({ outcome: e.target.value })}>
            {OUTCOMES.map((o) => (<option key={o} value={o}>{titleCase(o)}</option>))}
          </Select>
        </Field>
      </div>
      <Field label="Action taken" hint="e.g. drug withdrawn, dose reduced, referred to hospital."><Textarea rows={2} value={form.action_taken} onChange={(e) => set({ action_taken: e.target.value })} /></Field>
      <Field label="Reporter name" required error={err?.errors.reporter_name?.[0]}><Input value={form.reporter_name} onChange={(e) => set({ reporter_name: e.target.value })} /></Field>
      {err && !Object.keys(err.errors).length && <InlineError error={save.error} />}
      <div className="flex justify-end gap-2">
        <Button onClick={onCancel}>Cancel</Button>
        <Button variant="primary" disabled={!valid || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : report ? 'Save changes' : 'Save draft'}</Button>
      </div>
    </div>
  )
}
