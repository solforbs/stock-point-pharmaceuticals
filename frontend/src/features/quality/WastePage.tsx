import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { MoneyCell, QtyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, Field, Input, Select, Textarea } from '../../components/ui/primitives'
import { UserPicker } from '../../components/UserPicker'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import { apiGet, apiPost } from '../../lib/api'
import { formatDate, formatDateTime, titleCase } from '../../lib/format'
import { useStores } from '../../lib/hooks'
import { usePermissions } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Paginated, WasteDisposal } from '../../lib/types'
import { BatchLinesEditor, batchLinesPayload, batchLinesValid, type BatchLine } from '../inventory/BatchLinesEditor'

const REASONS = ['EXPIRED', 'DAMAGED', 'RECALLED', 'EXCURSION', 'CONTAMINATED']

/** Part 11.3 — disposal batches with method, contractor, certificate and two witnesses; posting writes the stock off. */
export default function WastePage() {
  const [params, setParams] = useSearchParams()
  const queryClient = useQueryClient()
  const perms = usePermissions()
  const stores = useStores()
  const [status, setStatus] = useState('')
  const [reasonFilter, setReasonFilter] = useState('')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const [storeId, setStoreId] = useState('')
  const [reason, setReason] = useState('EXPIRED')
  const [method, setMethod] = useState('')
  const [notes, setNotes] = useState('')
  const [recallId, setRecallId] = useState(params.get('recall') ?? '')
  const [lines, setLines] = useState<BatchLine[]>([])
  const selectedId = params.get('disposal')

  useEffect(() => {
    if (params.get('recall')) setCreating(true)
  }, [params])

  const list = useQuery({
    queryKey: ['waste', 'list', status, reasonFilter, page],
    queryFn: () => apiGet<Paginated<WasteDisposal>>('/api/waste-disposals', { status, reason: reasonFilter, page, per_page: 50 }),
    placeholderData: (prev) => prev,
  })

  const create = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<WasteDisposal>('/api/waste-disposals', { store_id: storeId, reason, recall_id: recallId || null, disposal_method: method || null, notes: notes || null, lines: batchLinesPayload(lines) }),
    onSuccess: (d) => {
      toast.success(`Disposal ${d.doc_number} drafted`, 'Two witnesses are needed to post it.')
      queryClient.invalidateQueries({ queryKey: ['waste'] })
      setCreating(false)
      setLines([])
      setParams({ disposal: d.id })
    },
  })

  const columns: Column<WasteDisposal>[] = [
    { key: 'doc', header: 'Document', render: (d) => <span className="font-semibold tabular">{d.doc_number}</span>, sortValue: (d) => d.doc_number },
    { key: 'store', header: 'Store', render: (d) => d.store?.code ?? '—' },
    { key: 'reason', header: 'Reason', render: (d) => <StatusBadge status={d.reason} tone={d.reason === 'RECALLED' ? 'purple' : d.reason === 'EXCURSION' ? 'cold' : 'red'} /> },
    { key: 'status', header: 'Status', render: (d) => <StatusBadge status={d.status} /> },
    { key: 'lines', header: 'Lines', align: 'right', render: (d) => <span className="tabular">{d.lines_count ?? '—'}</span> },
    { key: 'value', header: 'Value', align: 'right', render: (d) => <MoneyCell value={d.total_value} />, sortValue: (d) => Number(d.total_value) },
    { key: 'cert', header: 'Certificate', render: (d) => d.certificate_reference ?? '—' },
    { key: 'posted', header: 'Posted', render: (d) => formatDateTime(d.posted_at) },
  ]

  return (
    <Page>
      <PageHeader
        parent="Quality & Compliance"
        title="Waste & Disposal"
        subtitle="Disposal batches by reason, with method, contractor, certificate and two-signatory approval. Posting writes the stock off to the dedicated account."
        actions={
          perms.has('stock.adjust') ? (
            <div id="tour-waste-create">
              <Button variant="primary" onClick={() => setCreating(true)}>New disposal</Button>
            </div>
          ) : null
        }
      />
      <div id="tour-waste-filters">
        <FilterBar>
          <Field label="Status"><Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}><option value="">All</option><option value="DRAFT">Draft</option><option value="POSTED">Posted</option></Select></Field>
          <Field label="Reason"><Select value={reasonFilter} onChange={(e) => { setReasonFilter(e.target.value); setPage(1) }}><option value="">All</option>{REASONS.map((r) => (<option key={r} value={r}>{titleCase(r)}</option>))}</Select></Field>
        </FilterBar>
      </div>
      <div id="tour-waste-table" className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(d) => d.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(d) => setParams({ disposal: d.id })} selectedKey={selectedId} emptyTitle="No disposals" />
        <Pagination page={list.data} onPage={setPage} />
      </div>

      <Drawer open={creating} onClose={() => setCreating(false)} title="New waste disposal" width={820}>
        <div className="space-y-4">
          <div className="grid grid-cols-3 gap-3">
            <Field label="Store" required>
              <Select value={storeId} onChange={(e) => { setStoreId(e.target.value); setLines([]) }}><option value="">Choose…</option>{(stores.data ?? []).map((s) => (<option key={s.id} value={s.id}>{s.code} · {s.name}</option>))}</Select>
            </Field>
            <Field label="Reason" required>
              <Select value={reason} onChange={(e) => setReason(e.target.value)}>{REASONS.map((r) => (<option key={r} value={r}>{titleCase(r)}</option>))}</Select>
            </Field>
            <Field label="Disposal method"><Input value={method} onChange={(e) => setMethod(e.target.value)} placeholder="Incineration" /></Field>
            <Field label="Recall id (if disposing recalled stock)" className="col-span-2"><Input value={recallId} onChange={(e) => setRecallId(e.target.value)} placeholder="UUID" /></Field>
            <Field label="Notes"><Textarea rows={1} value={notes} onChange={(e) => setNotes(e.target.value)} /></Field>
          </div>
          <Field label="Lines" required hint="Any batch state can be disposed (expired, quarantined, recalled…). Quantities in base units.">
            <BatchLinesEditor lines={lines} onChange={setLines} storeId={storeId} />
          </Field>
          {create.isError && <InlineError error={create.error} />}
          <div className="flex justify-end gap-2">
            <Button onClick={() => setCreating(false)}>Cancel</Button>
            <Button variant="primary" disabled={!storeId || !batchLinesValid(lines) || create.isPending} onClick={() => create.mutate()}>{create.isPending ? 'Saving…' : 'Create disposal'}</Button>
          </div>
        </div>
      </Drawer>

      <DisposalDrawer id={selectedId} onClose={() => setParams({})} />
    </Page>
  )
}

function DisposalDrawer({ id, onClose }: { id: string | null; onClose: () => void }) {
  const queryClient = useQueryClient()
  const perms = usePermissions()
  const { data: user } = useCurrentUser()
  const [form, setForm] = useState({ disposal_method: '', disposal_contractor: '', certificate_reference: '', ppb_reference: '', witnessed_by_1: '', witnessed_by_2: '' })
  const disposal = useQuery({ queryKey: ['waste', id], queryFn: () => apiGet<WasteDisposal>(`/api/waste-disposals/${id}`), enabled: !!id })

  useEffect(() => {
    const d = disposal.data
    if (!d) return
    setForm({
      disposal_method: d.disposal_method ?? '',
      disposal_contractor: d.disposal_contractor ?? '',
      certificate_reference: d.certificate_reference ?? '',
      ppb_reference: d.ppb_reference ?? '',
      witnessed_by_1: d.witnessed_by_1 ? String(d.witnessed_by_1) : user?.id ? String(user.id) : '',
      witnessed_by_2: d.witnessed_by_2 ? String(d.witnessed_by_2) : '',
    })
  }, [disposal.data, user?.id])

  const post = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<WasteDisposal>(`/api/waste-disposals/${id}/post`, { ...form, disposal_method: form.disposal_method || null, disposal_contractor: form.disposal_contractor || null, certificate_reference: form.certificate_reference || null, ppb_reference: form.ppb_reference || null, witnessed_by_1: form.witnessed_by_1 ? Number(form.witnessed_by_1) : null, witnessed_by_2: form.witnessed_by_2 ? Number(form.witnessed_by_2) : null }),
    onSuccess: (d) => {
      toast.success(`${d.doc_number} posted`, 'Stock written off; the batch is DISPOSED once emptied.')
      queryClient.invalidateQueries({ queryKey: ['waste'] })
      queryClient.invalidateQueries({ queryKey: ['inventory'] })
      queryClient.invalidateQueries({ queryKey: ['batches'] })
    },
  })

  const d = disposal.data
  const draft = d?.status === 'DRAFT'
  const set = (patch: Partial<typeof form>) => setForm({ ...form, ...patch })
  const witnessesOk = form.witnessed_by_1 && form.witnessed_by_2 && form.witnessed_by_1 !== form.witnessed_by_2

  return (
    <Drawer open={!!id} onClose={onClose} title={d?.doc_number ?? 'Disposal'} subtitle={d ? `${d.store?.code ?? ''} · ${titleCase(d.reason)}` : undefined} width={780}>
      {disposal.isLoading && <LoadingSkeleton />}
      {disposal.isError && <InlineError error={disposal.error} />}
      {d && (
        <div className="space-y-4">
          <div className="flex items-center gap-2"><StatusBadge status={d.status} /><span className="tabular text-sm font-medium text-slate-700">Value: <MoneyCell value={d.total_value} symbol className="font-bold text-slate-900" /></span></div>
          <table className="ui-table">
            <thead><tr><th>Product</th><th>Batch</th><th className="text-right">Qty (base)</th><th className="text-right">Unit cost</th><th className="text-right">Value</th></tr></thead>
            <tbody>
              {(d.lines ?? []).map((l) => (
                <tr key={l.id}><td>{l.product?.name ?? l.product_id.slice(0, 8)}</td><td className="tabular font-mono">{l.batch?.batch_number ?? l.batch_id.slice(0, 8)}{l.batch && <div className="text-xs text-slate-500 font-sans">exp {formatDate(l.batch.expiry_date)} · {titleCase(l.batch.status)}</div>}</td><td className="text-right"><QtyCell value={l.qty_base} /></td><td className="text-right"><MoneyCell value={l.unit_cost} /></td><td className="text-right"><MoneyCell value={l.line_value} /></td></tr>
              ))}
            </tbody>
          </table>
          {draft ? (
            <div className="space-y-3">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <Field label="Disposal method"><Input value={form.disposal_method} onChange={(e) => set({ disposal_method: e.target.value })} /></Field>
                <Field label="Contractor"><Input value={form.disposal_contractor} onChange={(e) => set({ disposal_contractor: e.target.value })} /></Field>
                <Field label="Certificate reference"><Input value={form.certificate_reference} onChange={(e) => set({ certificate_reference: e.target.value })} /></Field>
                <Field label="PPB reference"><Input value={form.ppb_reference} onChange={(e) => set({ ppb_reference: e.target.value })} /></Field>
                <Field label="Witness 1" required hint="Prefilled with you."><UserPicker value={form.witnessed_by_1 ? Number(form.witnessed_by_1) : null} onChange={(id) => set({ witnessed_by_1: id ? String(id) : '' })} exclude={[form.witnessed_by_2 ? Number(form.witnessed_by_2) : null]} /></Field>
                <Field label="Witness 2" required hint="Must be a different person."><UserPicker value={form.witnessed_by_2 ? Number(form.witnessed_by_2) : null} onChange={(id) => set({ witnessed_by_2: id ? String(id) : '' })} exclude={[form.witnessed_by_1 ? Number(form.witnessed_by_1) : null]} /></Field>
              </div>
              {post.isError && <InlineError error={post.error} />}
              <div className="flex justify-end">
                <Button variant="danger" disabled={!perms.has('waste.approve') || !witnessesOk || post.isPending} onClick={() => post.mutate()} title={perms.has('waste.approve') ? undefined : 'Needs waste.approve'}>{post.isPending ? 'Posting…' : 'Post disposal (write off)'}</Button>
              </div>
            </div>
          ) : (
            <DescriptionList items={[{ label: 'Method', value: d.disposal_method ?? '—' }, { label: 'Contractor', value: d.disposal_contractor ?? '—' }, { label: 'Certificate', value: d.certificate_reference ?? '—' }, { label: 'PPB reference', value: d.ppb_reference ?? '—' }, { label: 'Witnesses', value: `${d.witnessed_by_1 ?? '—'} and ${d.witnessed_by_2 ?? '—'}` }, { label: 'Posted', value: formatDateTime(d.posted_at) }]} />
          )}
        </div>
      )}
    </Drawer>
  )
}
