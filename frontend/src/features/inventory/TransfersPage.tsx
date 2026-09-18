import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { Modal } from '../../components/ui/Modal'
import { QtyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, Field, Select, Textarea } from '../../components/ui/primitives'
import { apiGet, apiPost } from '../../lib/api'
import { formatDate, formatDateTime, titleCase } from '../../lib/format'
import { useStores } from '../../lib/hooks'
import { usePermissions } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Paginated, StockTransfer } from '../../lib/types'
import { BatchLinesEditor, batchLinesPayload, batchLinesValid, type BatchLine } from './BatchLinesEditor'

const STATUSES = ['DRAFT', 'APPROVED', 'DISPATCHED', 'RECEIVED', 'DISCREPANCY']
const ADJ_REASONS = ['BREAKAGE', 'THEFT', 'EXPIRY', 'SAMPLING', 'CORRECTION_OF_ERROR', 'DONATION', 'COLD_CHAIN_LOSS']

/** Part 7.6 — DRAFT → APPROVED → DISPATCHED (in transit) → RECEIVED, or DISCREPANCY resolved by a ledger-true correction. */
export default function TransfersPage() {
  const [params, setParams] = useSearchParams()
  const queryClient = useQueryClient()
  const perms = usePermissions()
  const stores = useStores()
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const [fromStore, setFromStore] = useState('')
  const [toStore, setToStore] = useState('')
  const [lines, setLines] = useState<BatchLine[]>([])
  const selectedId = params.get('transfer')

  const list = useQuery({
    queryKey: ['transfers', 'list', status, page],
    queryFn: () => apiGet<Paginated<StockTransfer>>('/api/inventory/transfers', { status, page, per_page: 50 }),
    placeholderData: (prev) => prev,
  })

  const create = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<StockTransfer>('/api/inventory/transfers', { from_store_id: fromStore, to_store_id: toStore, lines: batchLinesPayload(lines) }),
    onSuccess: (t) => {
      toast.success(`Transfer ${t.doc_number} drafted`)
      queryClient.invalidateQueries({ queryKey: ['transfers'] })
      setCreating(false)
      setLines([])
      setParams({ transfer: t.id })
    },
  })

  const columns: Column<StockTransfer>[] = [
    { key: 'doc', header: 'Document', render: (t) => <span className="font-semibold tabular">{t.doc_number}</span>, sortValue: (t) => t.doc_number },
    { key: 'from', header: 'From', render: (t) => t.from_store?.code ?? '—' },
    { key: 'to', header: 'To', render: (t) => t.to_store?.code ?? '—' },
    { key: 'status', header: 'Status', render: (t) => <StatusBadge status={t.status} /> },
    { key: 'lines', header: 'Lines', align: 'right', render: (t) => <span className="tabular">{t.lines_count ?? '—'}</span> },
    { key: 'dispatched', header: 'Dispatched', render: (t) => formatDateTime(t.dispatched_at) },
    { key: 'received', header: 'Received', render: (t) => formatDateTime(t.received_at) },
  ]

  return (
    <Page>
      <PageHeader parent="Inventory" title="Transfers" subtitle="Stock between stores of one entity: in transit while on the road, no journal." actions={perms.has('stock.transfer.create') ? <Button variant="primary" onClick={() => setCreating(true)}>New transfer</Button> : null} />
      <FilterBar>
        <Field label="Status">
          <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
            <option value="">All</option>
            {STATUSES.map((s) => (<option key={s} value={s}>{titleCase(s)}</option>))}
          </Select>
        </Field>
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(t) => t.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(t) => setParams({ transfer: t.id })} selectedKey={selectedId} emptyTitle="No transfers" />
        <Pagination page={list.data} onPage={setPage} />
      </div>

      <Drawer open={creating} onClose={() => setCreating(false)} title="New stock transfer" width={820}>
        <div className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <Field label="From store" required>
              <Select value={fromStore} onChange={(e) => { setFromStore(e.target.value); setLines([]) }}>
                <option value="">Choose…</option>
                {(stores.data ?? []).map((s) => (<option key={s.id} value={s.id}>{s.code} · {s.name}</option>))}
              </Select>
            </Field>
            <Field label="To store" required>
              <Select value={toStore} onChange={(e) => setToStore(e.target.value)}>
                <option value="">Choose…</option>
                {(stores.data ?? []).filter((s) => s.id !== fromStore).map((s) => (<option key={s.id} value={s.id}>{s.code} · {s.name}</option>))}
              </Select>
            </Field>
          </div>
          <Field label="Lines" required hint="Quantities in base units, per batch, from the source store's balances.">
            <BatchLinesEditor lines={lines} onChange={setLines} storeId={fromStore} statuses={['RELEASED', 'PENDING_QC', 'QUARANTINED']} />
          </Field>
          {create.isError && <InlineError error={create.error} />}
          <div className="flex justify-end gap-2">
            <Button onClick={() => setCreating(false)}>Cancel</Button>
            <Button variant="primary" disabled={!fromStore || !toStore || !batchLinesValid(lines) || create.isPending} onClick={() => create.mutate()}>{create.isPending ? 'Saving…' : 'Create transfer'}</Button>
          </div>
        </div>
      </Drawer>

      <TransferDrawer id={selectedId} onClose={() => setParams({})} />
    </Page>
  )
}

function TransferDrawer({ id, onClose }: { id: string | null; onClose: () => void }) {
  const queryClient = useQueryClient()
  const perms = usePermissions()
  const [received, setReceived] = useState<Record<string, string>>({})
  const [resolving, setResolving] = useState(false)
  const [resolution, setResolution] = useState('LOST')
  const [reason, setReason] = useState('')
  const [adjReason, setAdjReason] = useState('BREAKAGE')
  const transfer = useQuery({ queryKey: ['transfers', id], queryFn: () => apiGet<StockTransfer>(`/api/inventory/transfers/${id}`), enabled: !!id })

  function done(t: StockTransfer, msg: string) {
    toast.success(`${t.doc_number} ${msg}`)
    queryClient.invalidateQueries({ queryKey: ['transfers'] })
    queryClient.invalidateQueries({ queryKey: ['inventory'] })
    queryClient.invalidateQueries({ queryKey: ['products'] })
  }
  const approve = useMutation({ mutationFn: () => apiPost<StockTransfer>(`/api/inventory/transfers/${id}/approve`), onSuccess: (t) => done(t, 'approved') })
  const dispatch = useMutation({ mutationFn: () => apiPost<StockTransfer>(`/api/inventory/transfers/${id}/dispatch`), onSuccess: (t) => done(t, 'dispatched — now in transit') })
  const receive = useMutation({
    mutationFn: () => apiPost<StockTransfer>(`/api/inventory/transfers/${id}/receive`, { lines: Object.entries(received).filter(([, v]) => v !== '').map(([lineId, qty]) => ({ id: lineId, qty_received: qty })) }),
    onSuccess: (t) => done(t, t.status === 'DISCREPANCY' ? 'received short — discrepancy raised' : 'received'),
  })
  const resolve = useMutation({
    mutationFn: () => apiPost<StockTransfer>(`/api/inventory/transfers/${id}/resolve`, { resolution, reason, adjustment_reason_code: resolution === 'LOST' ? adjReason : null }),
    onSuccess: (t) => { setResolving(false); done(t, 'discrepancy resolved') },
  })

  const t = transfer.data
  return (
    <Drawer
      open={!!id}
      onClose={onClose}
      title={t?.doc_number ?? 'Transfer'}
      subtitle={t ? `${t.from_store?.code ?? ''} → ${t.to_store?.code ?? ''}` : undefined}
      width={760}
      actions={
        t ? (
          <div className="flex gap-2">
            {t.status === 'DRAFT' && perms.has('stock.transfer.approve') && <Button size="sm" variant="success" disabled={approve.isPending} onClick={() => approve.mutate()}>Approve</Button>}
            {t.status === 'APPROVED' && perms.has('stock.transfer.dispatch') && <Button size="sm" variant="primary" disabled={dispatch.isPending} onClick={() => dispatch.mutate()}>Dispatch</Button>}
            {t.status === 'DISPATCHED' && perms.has('stock.transfer.receive') && <Button size="sm" variant="success" disabled={receive.isPending} onClick={() => receive.mutate()}>Receive</Button>}
            {t.status === 'DISCREPANCY' && perms.has('stock.transfer.approve') && <Button size="sm" variant="danger" onClick={() => setResolving(true)}>Resolve discrepancy</Button>}
          </div>
        ) : null
      }
    >
      {transfer.isLoading && <LoadingSkeleton />}
      {transfer.isError && <InlineError error={transfer.error} />}
      {(approve.isError || dispatch.isError || receive.isError) && <InlineError error={approve.error ?? dispatch.error ?? receive.error} className="mb-3" />}
      {t && (
        <div className="space-y-4">
          <div className="flex items-center gap-2"><StatusBadge status={t.status} /></div>
          <DescriptionList items={[{ label: 'Created', value: formatDateTime(t.created_at) }, { label: 'Dispatched', value: formatDateTime(t.dispatched_at) }, { label: 'Received', value: formatDateTime(t.received_at) }]} />
          <table className="ui-table">
            <thead><tr><th>Product</th><th>Batch</th><th className="text-right">Dispatched</th><th className="text-right">Received</th></tr></thead>
            <tbody>
              {(t.lines ?? []).map((l) => (
                <tr key={l.id}>
                  <td>{l.product?.name ?? l.product_id.slice(0, 8)}</td>
                  <td className="tabular">{l.batch?.batch_number ?? l.batch_id.slice(0, 8)}{l.batch && <div className="text-[10.5px] text-[var(--text-muted)]">exp {formatDate(l.batch.expiry_date)}</div>}</td>
                  <td className="text-right"><QtyCell value={l.qty_dispatched} /></td>
                  <td className="text-right">
                    {t.status === 'DISPATCHED' && perms.has('stock.transfer.receive') ? (
                      <input value={received[l.id] ?? ''} placeholder={String(Number(l.qty_dispatched))} onChange={(e) => setReceived({ ...received, [l.id]: e.target.value.replace(/[^\d.]/g, '') })} className="ui-input h-7 w-24 tabular text-right" />
                    ) : (
                      <QtyCell value={l.qty_received} className={l.qty_received !== null && Number(l.qty_received) < Number(l.qty_dispatched) ? 'text-[var(--status-red)] font-bold' : ''} />
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {t.status === 'DISPATCHED' && <p className="text-[11px] text-[var(--text-muted)]">Leave a quantity blank to receive the full dispatched amount. A short receipt raises a discrepancy; the shortfall stays in transit until resolved.</p>}
        </div>
      )}
      <Modal
        open={resolving}
        onClose={() => setResolving(false)}
        title="Resolve discrepancy"
        footer={<><Button onClick={() => setResolving(false)}>Cancel</Button><Button variant="danger" disabled={reason.trim().length < 5 || resolve.isPending} onClick={() => resolve.mutate()}>{resolve.isPending ? 'Resolving…' : 'Resolve'}</Button></>}
      >
        <div className="space-y-3">
          <Field label="Resolution" required>
            <Select value={resolution} onChange={(e) => setResolution(e.target.value)}>
              <option value="FOUND_AT_SOURCE">Found at source (never left)</option>
              <option value="FOUND_AT_DESTINATION">Found at destination (miscounted on receipt)</option>
              <option value="LOST">Lost in transit (write off with an adjustment)</option>
            </Select>
          </Field>
          {resolution === 'LOST' && (
            <Field label="Adjustment reason code" required>
              <Select value={adjReason} onChange={(e) => setAdjReason(e.target.value)}>{ADJ_REASONS.map((r) => (<option key={r} value={r}>{titleCase(r)}</option>))}</Select>
            </Field>
          )}
          <Field label="Reason" required hint="At least 5 characters; recorded in the audit log."><Textarea rows={3} value={reason} onChange={(e) => setReason(e.target.value)} /></Field>
          {resolve.isError && <InlineError error={resolve.error} />}
        </div>
      </Modal>
    </Drawer>
  )
}
