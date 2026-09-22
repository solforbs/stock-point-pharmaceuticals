import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ArrowRightLeft, MapPin, Package, Plus } from 'lucide-react'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { Modal } from '../../components/ui/Modal'
import { QtyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, DrawerFooter, Field, FormSection, PrimaryAction, Select, Textarea } from '../../components/ui/primitives'
import { apiGet, apiPost } from '../../lib/api'
import { formatDate, formatDateTime, titleCase } from '../../lib/format'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import { useStores } from '../../lib/hooks'
import { usePermission, usePermissions } from '../../lib/permissions'
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
      <PageHeader
        parent="Inventory & Storage"
        title="Inter-Store Stock Transfers"
        subtitle="Move inventory securely between facility branches and quarantine warehouses with custody tracking"
        actions={
          perms.has('stock.transfer.create') ? (
            <div id="tour-transfers-new">
              <PrimaryAction icon={Plus} onClick={() => setCreating(true)}>
                New transfer
              </PrimaryAction>
            </div>
          ) : null
        }
      />
      <BranchExpansionNote />
      <div id="tour-transfers-filters">
        <FilterBar>
          <Field label="Status">
            <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
              <option value="">All Statuses</option>
              {STATUSES.map((s) => (<option key={s} value={s}>{titleCase(s)}</option>))}
            </Select>
          </Field>
        </FilterBar>
      </div>
      <div id="tour-transfers-table" className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(t) => t.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(t) => setParams({ transfer: t.id })} selectedKey={selectedId} emptyTitle="No transfers" />
        <Pagination page={list.data} onPage={setPage} />
      </div>

      <Drawer
        open={creating}
        onClose={() => setCreating(false)}
        title="New Stock Transfer"
        subtitle="Transfer specific batches between stores without creating fiscal journal entries"
        width={820}
        footer={
          <DrawerFooter
            badge={`${lines.length} lines`}
            onCancel={() => setCreating(false)}
            onSubmit={() => create.mutate()}
            submitLabel="Create transfer"
            disabled={!fromStore || !toStore || !batchLinesValid(lines) || create.isPending}
            isPending={create.isPending}
          />
        }
      >
        <div className="space-y-4">
          <FormSection
            title="Source & Destination Stores"
            description="Select origin storage location and destination pharmacy branch"
            icon={ArrowRightLeft}
            badge="Required"
          >
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
              <Field label="From Store (Origin)" required>
                <Select value={fromStore} onChange={(e) => { setFromStore(e.target.value); setLines([]) }}>
                  <option value="">Choose origin store…</option>
                  {(stores.data ?? []).map((s) => (<option key={s.id} value={s.id}>{s.code} · {s.name}</option>))}
                </Select>
              </Field>
              <Field label="To Store (Destination)" required>
                <Select value={toStore} onChange={(e) => setToStore(e.target.value)}>
                  <option value="">Choose destination store…</option>
                  {(stores.data ?? []).filter((s) => s.id !== fromStore).map((s) => (<option key={s.id} value={s.id}>{s.code} · {s.name}</option>))}
                </Select>
              </Field>
            </div>
          </FormSection>

          <FormSection
            title="Batch Allocations"
            description="Quantities in base units per batch, drawn from active source store stock balances"
            icon={Package}
            badge={`${lines.length} lines`}
          >
            <BatchLinesEditor lines={lines} onChange={setLines} storeId={fromStore} statuses={['RELEASED', 'PENDING_QC', 'QUARANTINED']} />
          </FormSection>

          {create.isError && <InlineError error={create.error} />}
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

  const { data: currentUser } = useCurrentUser()
  const t = transfer.data
  const isCreator = !!currentUser && !!t && t.requested_by === currentUser.id

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
            {t.status === 'DRAFT' && perms.has('stock.transfer.approve') && (
              <Button
                size="sm"
                variant="success"
                disabled={approve.isPending || isCreator}
                onClick={() => approve.mutate()}
                title={isCreator ? 'You requested this transfer. Another authorized user must approve it (Segregation of Duties).' : undefined}
              >
                Approve
              </Button>
            )}
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
          {t.status === 'DRAFT' && isCreator && (
            <div className="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">
              <span className="font-semibold">Segregation of Duties:</span> You created this transfer request. Another user with transfer approval permission must review and approve it.
            </div>
          )}
          <DescriptionList items={[{ label: 'Created', value: formatDateTime(t.created_at) }, { label: 'Dispatched', value: formatDateTime(t.dispatched_at) }, { label: 'Received', value: formatDateTime(t.received_at) }]} />
          <table className="ui-table">
            <thead><tr><th>Product</th><th>Batch</th><th className="text-right">Dispatched</th><th className="text-right">Received</th></tr></thead>
            <tbody>
              {(t.lines ?? []).map((l) => (
                <tr key={l.id}>
                  <td>{l.product?.name ?? l.product_id.slice(0, 8)}</td>
                  <td className="tabular">{l.batch?.batch_number ?? l.batch_id.slice(0, 8)}{l.batch && <div className="text-xs text-slate-500 tabular">exp {formatDate(l.batch.expiry_date)}</div>}</td>
                  <td className="text-right"><QtyCell value={l.qty_dispatched} /></td>
                  <td className="text-right">
                    {t.status === 'DISPATCHED' && perms.has('stock.transfer.receive') ? (
                      <input value={received[l.id] ?? ''} placeholder={String(Number(l.qty_dispatched))} onChange={(e) => setReceived({ ...received, [l.id]: e.target.value.replace(/[^\d.]/g, '') })} className="ui-input h-7 w-24 tabular text-right text-sm" />
                    ) : (
                      <QtyCell value={l.qty_received} className={l.qty_received !== null && Number(l.qty_received) < Number(l.qty_dispatched) ? 'text-rose-600 font-bold' : ''} />
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {t.status === 'DISPATCHED' && <p className="text-xs text-slate-500">Leave a quantity blank to receive the full dispatched amount. A short receipt raises a discrepancy; the shortfall stays in transit until resolved.</p>}
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

/**
 * With one branch, transfers only move stock between that branch's stores
 * (main warehouse to the retail counter, into quarantine). Branch-to-branch
 * transfers come into play once a second branch exists; until then the page
 * says so and shows the branches the business plans to open.
 */
function BranchExpansionNote() {
  const { data: user } = useCurrentUser()
  const canAddBranch = usePermission('admin.settings')
  const branches = user?.branches ?? []
  if (branches.length > 1) return null

  return (
    <div className="ui-card p-4 flex flex-col sm:flex-row sm:items-center gap-3 border-dashed">
      <MapPin size={20} className="text-blue-600 shrink-0" />
      <div className="text-sm text-slate-600 flex-1">
        <div className="font-semibold text-slate-800">One branch today: {branches[0]?.name ?? 'your main branch'}</div>
        Transfers currently move stock between this branch's stores, for example Main Warehouse to the Retail counter.
        Branch-to-branch transfers switch on when a second branch is added.
        <div className="mt-2 flex flex-wrap gap-1.5 text-xs">
          <span className="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-600">Planned: second branch in Lodwar town</span>
          <span className="rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-600">Planned: branches outside Lodwar</span>
        </div>
      </div>
      {canAddBranch && (
        <Link to="/admin/branches" className="text-sm font-semibold text-blue-600 hover:underline shrink-0">Add a branch</Link>
      )}
    </div>
  )
}
