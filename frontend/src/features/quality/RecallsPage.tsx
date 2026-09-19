import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Printer } from 'lucide-react'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useDebounced } from '../../components/ProductSearch'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { Modal } from '../../components/ui/Modal'
import { QtyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Card, DescriptionList, Field, Input, Select, Textarea } from '../../components/ui/primitives'
import { apiGet, apiPost } from '../../lib/api'
import { formatDate, formatDateTime, titleCase } from '../../lib/format'
import { formatPct, formatQty } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Paginated, ProductBatch, Recall, RecallTrace } from '../../lib/types'

const STATUSES = ['INITIATED', 'SCOPED', 'BLOCKED', 'NOTIFIED', 'RECOVERING', 'RECONCILED', 'DISPOSITIONED', 'CLOSED']

/** Part 8.4 — where is the stock, who received it, what came back. Trace → block → notify → recover → reconcile → disposition → close. */
export default function RecallsPage() {
  const [params, setParams] = useSearchParams()
  const queryClient = useQueryClient()
  const canInitiate = usePermission('recall.initiate')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const [source, setSource] = useState('MANUFACTURER')
  const [extRef, setExtRef] = useState('')
  const [reason, setReason] = useState('')
  const [batchQuery, setBatchQuery] = useState('')
  const [batches, setBatches] = useState<ProductBatch[]>([])
  const dq = useDebounced(batchQuery, 250)
  const selectedId = params.get('recall')

  const list = useQuery({ queryKey: ['recalls', 'list', status, page], queryFn: () => apiGet<Paginated<Recall>>('/api/recalls', { status, page, per_page: 50 }), placeholderData: (prev) => prev })
  const batchSearch = useQuery({ queryKey: ['batches', 'recall-search'], queryFn: () => apiGet<Paginated<ProductBatch>>('/api/batches', { per_page: 200 }), enabled: creating })
  const matches = (batchSearch.data?.data ?? []).filter((b) => !dq || b.batch_number.toLowerCase().includes(dq.toLowerCase()) || (b.product?.name ?? '').toLowerCase().includes(dq.toLowerCase())).slice(0, 25)

  const create = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<RecallTrace>('/api/recalls', { source, external_reference: extRef || null, reason, batch_ids: batches.map((b) => b.id) }),
    onSuccess: (trace) => {
      toast.success(`Recall ${trace.recall.doc_number} scoped`, `${trace.customers.length} customers received ${formatQty(trace.distributed_qty)} units.`)
      queryClient.invalidateQueries({ queryKey: ['recalls'] })
      setCreating(false)
      setBatches([])
      setReason('')
      setParams({ recall: trace.recall.id })
    },
  })

  const columns: Column<Recall>[] = [
    { key: 'doc', header: 'Recall', render: (r) => <span className="font-semibold font-mono tabular">{r.doc_number}</span>, sortValue: (r) => r.doc_number },
    { key: 'source', header: 'Source', render: (r) => <>{titleCase(r.source)}{r.external_reference && <div className="text-xs text-slate-500 font-mono tabular">{r.external_reference}</div>}</> },
    { key: 'status', header: 'Status', render: (r) => <StatusBadge status={r.status} tone={r.status === 'CLOSED' ? 'slate' : 'purple'} /> },
    { key: 'batches', header: 'Batches', align: 'right', render: (r) => <span className="tabular font-mono">{r.batches_count ?? '—'}</span> },
    { key: 'customers', header: 'Customers', align: 'right', render: (r) => <span className="tabular font-mono">{r.customers_count ?? '—'}</span> },
    { key: 'eff', header: 'Effectiveness', align: 'right', render: (r) => <span className="tabular font-mono">{r.effectiveness_pct ? formatPct(r.effectiveness_pct) : '—'}</span> },
    { key: 'initiated', header: 'Initiated', render: (r) => formatDateTime(r.initiated_at), sortValue: (r) => r.initiated_at ?? '' },
  ]

  return (
    <Page>
      <PageHeader parent="Quality & Compliance" title="Recalls" subtitle="Initiate, scope, block, notify, recover, reconcile, disposition and close — every step from posted data." actions={canInitiate ? <Button variant="danger" onClick={() => setCreating(true)}>Initiate recall</Button> : null} />
      <FilterBar>
        <Field label="Status"><Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}><option value="">All</option>{STATUSES.map((s) => (<option key={s} value={s}>{titleCase(s)}</option>))}</Select></Field>
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(r) => r.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(r) => setParams({ recall: r.id })} selectedKey={selectedId} emptyTitle="No recalls" />
        <Pagination page={list.data} onPage={setPage} />
      </div>

      <Drawer open={creating} onClose={() => setCreating(false)} title="Initiate a recall" width={720}>
        <div className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <Field label="Source" required><Select value={source} onChange={(e) => setSource(e.target.value)}>{['MANUFACTURER', 'PPB', 'INTERNAL'].map((s) => (<option key={s} value={s}>{titleCase(s)}</option>))}</Select></Field>
            <Field label="External reference"><Input value={extRef} onChange={(e) => setExtRef(e.target.value)} placeholder="PPB/RC/2026/17" /></Field>
            <Field label="Reason" required className="col-span-2"><Textarea rows={2} value={reason} onChange={(e) => setReason(e.target.value)} placeholder="At least 5 characters" /></Field>
          </div>
          <Field label="Batches in scope" required>
            <Input placeholder="Filter by batch number or product…" value={batchQuery} onChange={(e) => setBatchQuery(e.target.value)} />
            <div className="ui-card mt-2 max-h-56 overflow-y-auto">
              {matches.map((b) => {
                const chosen = batches.some((x) => x.id === b.id)
                return (
                  <label key={b.id} className="flex items-center gap-2 px-3 py-1.5 border-b border-slate-100 last:border-b-0 text-xs cursor-pointer hover:bg-slate-50">
                    <input type="checkbox" checked={chosen} onChange={() => setBatches(chosen ? batches.filter((x) => x.id !== b.id) : [...batches, b])} />
                    <span className="tabular font-semibold font-mono">{b.batch_number}</span>
                    <span className="flex-1 truncate text-slate-800">{b.product?.name}</span>
                    <span className="text-slate-500">exp {formatDate(b.expiry_date)}</span>
                    <StatusBadge status={b.status} />
                  </label>
                )
              })}
              {matches.length === 0 && <div className="px-3 py-2 text-xs text-slate-500">{batchSearch.isLoading ? 'Loading…' : 'No batches match.'}</div>}
            </div>
            <div className="text-xs text-slate-500 mt-1">{batches.length} selected: {batches.map((b) => b.batch_number).join(', ')}</div>
          </Field>
          {create.isError && <InlineError error={create.error} />}
          <div className="flex justify-end gap-2">
            <Button onClick={() => setCreating(false)}>Cancel</Button>
            <Button variant="danger" disabled={reason.trim().length < 5 || batches.length === 0 || create.isPending} onClick={() => create.mutate()}>{create.isPending ? 'Tracing…' : 'Initiate and trace'}</Button>
          </div>
        </div>
      </Drawer>

      <RecallDrawer id={selectedId} onClose={() => setParams({})} />
    </Page>
  )
}

function RecallDrawer({ id, onClose }: { id: string | null; onClose: () => void }) {
  const queryClient = useQueryClient()
  const canAct = usePermission('recall.initiate')
  const [notifyRef, setNotifyRef] = useState('')
  const [dispositionChoice, setDispositionChoice] = useState('DESTROY')
  const [lettersOpen, setLettersOpen] = useState(false)
  const trace = useQuery({ queryKey: ['recalls', id], queryFn: () => apiGet<RecallTrace>(`/api/recalls/${id}`), enabled: !!id })

  const act = useMutation({
    meta: { silent: true },
    mutationFn: ({ step, body }: { step: string; body?: unknown }) => apiPost<RecallTrace>(`/api/recalls/${id}/${step}`, body),
    onSuccess: (t, { step }) => {
      toast.success(`${t.recall.doc_number}: ${step} done`, `Status ${titleCase(t.recall.status)}`)
      queryClient.setQueryData(['recalls', id], t)
      queryClient.invalidateQueries({ queryKey: ['recalls'] })
      queryClient.invalidateQueries({ queryKey: ['inventory'] })
      queryClient.invalidateQueries({ queryKey: ['batches'] })
    },
  })

  const t = trace.data
  const r = t?.recall
  const next = r?.status === 'SCOPED' || r?.status === 'INITIATED' ? 'block' : r?.status === 'BLOCKED' ? 'notify' : r?.status === 'NOTIFIED' || r?.status === 'RECOVERING' ? 'reconcile' : r?.status === 'RECONCILED' ? 'disposition' : r?.status === 'DISPOSITIONED' ? 'close' : null

  return (
    <Drawer
      open={!!id}
      onClose={onClose}
      title={r?.doc_number ?? 'Recall'}
      subtitle={r ? `${titleCase(r.source)}${r.external_reference ? ` · ${r.external_reference}` : ''}` : undefined}
      width={900}
      actions={t ? <Button size="sm" onClick={() => setLettersOpen(true)}><Printer size={13} /> Customer letters</Button> : null}
    >
      {trace.isLoading && <LoadingSkeleton />}
      {trace.isError && <InlineError error={trace.error} />}
      {act.isError && <InlineError error={act.error} className="mb-3" />}
      {t && r && (
        <div className="space-y-4">
          <div className="flex items-center gap-2 flex-wrap">
            <StatusBadge status={r.status} tone={r.status === 'CLOSED' ? 'slate' : 'purple'} />
            <span className="text-sm text-slate-700">{r.reason}</span>
          </div>
          <div className="grid grid-cols-4 gap-3 text-center">
            {[
              ['Distributed', formatQty(t.distributed_qty)],
              ['Recovered', formatQty(t.recovered_qty)],
              ['Disposed', formatQty(t.disposed_qty)],
              ['Effectiveness', t.effectiveness_pct ? formatPct(t.effectiveness_pct) : '—'],
            ].map(([label, value]) => (
              <div key={label} className="ui-card p-3"><div className="ui-label !mb-0.5">{label}</div><div className="text-xl font-bold tabular text-slate-900">{value}</div></div>
            ))}
          </div>

          {canAct && next && (
            <Card title="Next step">
              <div className="p-4 flex flex-wrap items-end gap-3">
                {next === 'block' && <><p className="flex-1 text-sm text-slate-600">Block every batch in scope: status becomes RECALLED everywhere and the POS cannot touch it.</p><Button variant="danger" disabled={act.isPending} onClick={() => act.mutate({ step: 'block' })}>Block batches</Button></>}
                {next === 'notify' && <><Field label="Notification reference" className="flex-1"><Input value={notifyRef} onChange={(e) => setNotifyRef(e.target.value)} placeholder="LETTERS-2026-09-17" /></Field><Button variant="primary" disabled={act.isPending} onClick={() => act.mutate({ step: 'notify', body: { notification_reference: notifyRef || null } })}>Mark customers notified</Button></>}
                {next === 'reconcile' && <><p className="flex-1 text-sm text-slate-600">Customer returns citing this recall count as recovered. Reconcile when recoveries are complete to fix the effectiveness percentage.</p><Button variant="primary" disabled={act.isPending} onClick={() => act.mutate({ step: 'reconcile' })}>Reconcile</Button></>}
                {next === 'disposition' && <><Field label="Disposition" className="flex-1"><Select value={dispositionChoice} onChange={(e) => setDispositionChoice(e.target.value)}><option value="DESTROY">Destroy (witnessed waste disposal)</option><option value="RETURN_TO_SUPPLIER">Return to supplier</option></Select></Field><Button variant="primary" disabled={act.isPending} onClick={() => act.mutate({ step: 'disposition', body: { disposition: dispositionChoice } })}>Set disposition</Button></>}
                {next === 'close' && <><p className="flex-1 text-sm text-slate-600">Dispose of the recovered and on-hand stock first ({r.disposition === 'DESTROY' ? <Link to={`/quality/waste?recall=${r.id}`} className="text-blue-600 font-medium hover:underline">create the waste disposal</Link> : <Link to="/sell/returns?tab=supplier" className="text-blue-600 font-medium hover:underline">raise the supplier return</Link>}), then close.</p><Button variant="primary" disabled={act.isPending} onClick={() => act.mutate({ step: 'close' })}>Close recall</Button></>}
              </div>
            </Card>
          )}

          <Card title="Batches in scope">
            <table className="ui-table">
              <thead><tr><th>Batch</th><th>Product</th><th>Before</th><th className="text-right">On hand at scope</th><th className="text-right">On hand now</th><th className="text-right">Distributed</th><th className="text-right">Recovered</th><th className="text-right">Outstanding</th><th className="text-right">Disposed</th><th>By store</th></tr></thead>
              <tbody>
                {t.batches.map((b) => (
                  <tr key={b.id}>
                    <td className="tabular font-semibold font-mono">{b.batch?.batch_number ?? b.batch_id.slice(0, 8)}{b.batch && <div className="text-xs text-slate-500"><StatusBadge status={b.batch.status} /></div>}</td>
                    <td>{b.product?.name ?? '—'}</td>
                    <td>{titleCase(b.status_before) || '—'}</td>
                    <td className="text-right"><QtyCell value={b.on_hand_at_scope} /></td>
                    <td className="text-right"><QtyCell value={b.on_hand_now} /></td>
                    <td className="text-right"><QtyCell value={b.distributed_qty} /></td>
                    <td className="text-right"><QtyCell value={b.recovered_qty} /></td>
                    <td className="text-right"><QtyCell value={b.outstanding_qty} className={Number(b.outstanding_qty) > 0 ? 'text-rose-600 font-bold' : ''} /></td>
                    <td className="text-right"><QtyCell value={b.disposed_qty} /></td>
                    <td className="tabular font-mono text-xs text-slate-600">{b.stock_by_store.map((s) => `${s.store_code ?? s.store_id.slice(0, 6)} ${formatQty(s.on_hand)}`).join(', ') || '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </Card>

          <Card title="Customers who received the stock">
            <table className="ui-table">
              <thead><tr><th>Customer</th><th>Contact</th><th className="text-right">Distributed</th><th className="text-right">Recovered</th><th>Notified</th></tr></thead>
              <tbody>
                {t.customers.map((c) => (
                  <tr key={c.id}>
                    <td className="font-semibold">{c.customer_name_snapshot}{c.customer_id && <Link to={`/customers/list?customer=${c.customer_id}`} className="ml-1.5 text-xs text-blue-600 font-medium hover:underline">open</Link>}</td>
                    <td className="text-xs text-slate-600">{c.contact_snapshot ?? '—'}</td>
                    <td className="text-right"><QtyCell value={c.qty_distributed} /></td>
                    <td className="text-right"><QtyCell value={c.qty_recovered} className={Number(c.qty_recovered) >= Number(c.qty_distributed) ? 'text-emerald-600 font-bold' : ''} /></td>
                    <td>{c.notified_at ? <span className="tabular">{formatDateTime(c.notified_at)}{c.notification_reference ? ` · ${c.notification_reference}` : ''}</span> : <StatusBadge status="PENDING" label="Not yet" />}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </Card>
          <DescriptionList items={[{ label: 'Initiated', value: formatDateTime(r.initiated_at) }, { label: 'Blocked', value: formatDateTime(r.blocked_at) }, { label: 'Disposition', value: titleCase(r.disposition) || '—' }, { label: 'Closed', value: formatDateTime(r.closed_at) }]} />
        </div>
      )}

      <Modal open={lettersOpen} onClose={() => setLettersOpen(false)} title="Recall notification letters" width={720} footer={<><Button onClick={() => setLettersOpen(false)}>Close</Button><Button variant="primary" onClick={() => window.print()}><Printer size={13} /> Print</Button></>}>
        {t && r && (
          <div className="space-y-6" id="recall-letters">
            {t.customers.map((c) => (
              <div key={c.id} className="border-b border-dashed border-slate-300 pb-5 text-sm">
                <div className="font-bold text-slate-900">To: {c.customer_name_snapshot}</div>
                <div className="text-slate-500">{c.contact_snapshot ?? 'contact on file'}</div>
                <p className="mt-2"><b>URGENT PRODUCT RECALL — {r.doc_number}{r.external_reference ? ` (${r.external_reference})` : ''}</b></p>
                <p className="mt-1">Reason: {r.reason}</p>
                <p className="mt-1">Our records show you received <b>{formatQty(c.qty_distributed)}</b> base units of the affected batch(es): {t.batches.map((b) => `${b.batch?.batch_number ?? b.batch_id.slice(0, 8)} (${b.product?.name ?? ''})`).join(', ')}.</p>
                <p className="mt-1">Please quarantine any remaining stock immediately, stop dispensing or selling it, and return it to Stockpoint Pharma quoting this recall number. A credit note will be issued on receipt.</p>
                <p className="mt-1 text-slate-500">Issued {formatDate(new Date().toISOString())} · Stockpoint Pharma Quality Assurance</p>
              </div>
            ))}
            {t.customers.length === 0 && <p className="text-slate-500">No customers received this stock.</p>}
          </div>
        )}
      </Modal>
    </Drawer>
  )
}
