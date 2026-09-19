import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AlertTriangle, Package, Plus } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { ApprovalBar } from '../../components/ApprovalBar'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { MoneyCell, QtyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { DescriptionList, DrawerFooter, Field, FormSection, PrimaryAction, Select, Textarea } from '../../components/ui/primitives'
import { api, apiGet, apiPost } from '../../lib/api'
import { formatDate, formatDateTime, titleCase } from '../../lib/format'
import { useStores } from '../../lib/hooks'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Paginated, StockAdjustment } from '../../lib/types'
import { BatchLinesEditor, batchLinesPayload, batchLinesValid, type BatchLine } from './BatchLinesEditor'

const REASONS = ['BREAKAGE', 'THEFT', 'EXPIRY', 'SAMPLING', 'CORRECTION_OF_ERROR', 'DONATION', 'COLD_CHAIN_LOSS']

/** Part 7.8 — reasoned adjustments; above the value threshold they wait for a second person. PENDING is the approval queue. */
export default function AdjustmentsPage() {
  const [params, setParams] = useSearchParams()
  const queryClient = useQueryClient()
  const canAdjust = usePermission('stock.adjust')
  const showCost = usePermission('product.cost.view')
  const stores = useStores()
  const [status, setStatus] = useState(params.get('queue') === '1' ? 'PENDING' : '')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const [storeId, setStoreId] = useState('')
  const [reason, setReason] = useState('BREAKAGE')
  const [direction, setDirection] = useState<'-' | '+'>('-')
  const [notes, setNotes] = useState('')
  const [lines, setLines] = useState<BatchLine[]>([])
  const selectedId = params.get('adjustment')
  const effectiveStore = storeId || stores.data?.[0]?.id || ''

  const list = useQuery({
    queryKey: ['adjustments', 'list', status, page],
    queryFn: () => apiGet<Paginated<StockAdjustment>>('/api/inventory/adjustments', { approval_status: status, page, per_page: 50 }),
    placeholderData: (prev) => prev,
  })

  const create = useMutation({
    meta: { silent: true },
    mutationFn: async () => {
      const res = await api.post<StockAdjustment>('/api/inventory/adjustments', {
        store_id: effectiveStore,
        reason_code: reason,
        notes: notes || null,
        lines: batchLinesPayload(lines).map((l) => ({ ...l, qty_base: direction === '-' ? `-${l.qty_base}` : l.qty_base })),
      })
      return res.data
    },
    onSuccess: (adj) => {
      toast.success(adj.approval_status === 'APPROVED' ? `${adj.doc_number} posted` : `${adj.doc_number} awaits approval`, adj.approval_status === 'APPROVED' ? 'Stock moved.' : 'Above the value threshold; nothing moves until approved.')
      queryClient.invalidateQueries({ queryKey: ['adjustments'] })
      queryClient.invalidateQueries({ queryKey: ['inventory'] })
      queryClient.invalidateQueries({ queryKey: ['products'] })
      setCreating(false)
      setLines([])
      setNotes('')
      setParams({ adjustment: adj.id })
    },
  })

  const columns: Column<StockAdjustment>[] = [
    { key: 'doc', header: 'Document', render: (a) => <span className="font-semibold tabular">{a.doc_number}</span>, sortValue: (a) => a.doc_number },
    { key: 'store', header: 'Store', render: (a) => a.store?.code ?? '—' },
    { key: 'reason', header: 'Reason', render: (a) => titleCase(a.reason_code) },
    { key: 'status', header: 'Approval', render: (a) => <StatusBadge status={a.approval_status} /> },
    { key: 'lines', header: 'Lines', align: 'right', render: (a) => <span className="tabular">{a.lines_count ?? '—'}</span> },
    ...(showCost ? [{ key: 'value', header: 'Value', align: 'right' as const, render: (a: StockAdjustment) => <MoneyCell value={a.total_value} />, sortValue: (a: StockAdjustment) => Number(a.total_value ?? 0) }] : []),
    { key: 'created', header: 'Created', render: (a) => formatDateTime(a.created_at), sortValue: (a) => a.created_at ?? '' },
  ]

  return (
    <Page>
      <PageHeader
        parent="Inventory & Storage"
        title="Stock Adjustments"
        subtitle="Mandatory reason codes for write-offs, damages, and variances with secondary dual-control approval queues"
        actions={
          canAdjust ? (
            <PrimaryAction icon={Plus} onClick={() => setCreating(true)}>
              New adjustment
            </PrimaryAction>
          ) : null
        }
      />
      <FilterBar>
        <div className="flex items-center gap-1.5 p-1 bg-slate-100/80 rounded-xl border border-slate-200/60 w-fit">
          {[['PENDING', 'Approval queue'], ['', 'All'], ['APPROVED', 'Approved'], ['REJECTED', 'Rejected']].map(([v, label]) => (
            <button
              key={v}
              type="button"
              onClick={() => { setStatus(v); setPage(1) }}
              className={`h-7 px-3 rounded-lg text-xs font-bold transition-all ${status === v ? 'bg-white text-slate-900 shadow-xs border border-slate-200/80' : 'text-slate-600 hover:text-slate-900'}`}
            >
              {label}
            </button>
          ))}
        </div>
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(a) => a.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(a) => setParams({ adjustment: a.id })} selectedKey={selectedId} emptyTitle={status === 'PENDING' ? 'Nothing awaiting approval' : 'No adjustments'} />
        <Pagination page={list.data} onPage={setPage} />
      </div>

      <Drawer
        open={creating}
        onClose={() => setCreating(false)}
        title="New Stock Adjustment"
        subtitle="Log inventory write-offs or corrections against specific batches"
        width={840}
        footer={
          <DrawerFooter
            badge={`${lines.length} lines`}
            onCancel={() => setCreating(false)}
            onSubmit={() => create.mutate()}
            submitLabel="Submit adjustment"
            disabled={!effectiveStore || !batchLinesValid(lines) || create.isPending}
            isPending={create.isPending}
          />
        }
      >
        <div className="space-y-4">
          <FormSection
            title="Adjustment Details & Justification"
            description="Select target store, adjustment reason code, and direction"
            icon={AlertTriangle}
            badge="Audited"
          >
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
              <Field label="Store" required>
                <Select value={effectiveStore} onChange={(e) => { setStoreId(e.target.value); setLines([]) }}>
                  {(stores.data ?? []).map((s) => (<option key={s.id} value={s.id}>{s.code} · {s.name}</option>))}
                </Select>
              </Field>
              <Field label="Reason Code" required>
                <Select value={reason} onChange={(e) => setReason(e.target.value)}>
                  {REASONS.map((r) => (<option key={r} value={r}>{titleCase(r)}</option>))}
                </Select>
              </Field>
              <Field label="Direction" required>
                <Select value={direction} onChange={(e) => setDirection(e.target.value as '-' | '+')}>
                  <option value="-">Remove stock (−)</option>
                  <option value="+">Add stock (+)</option>
                </Select>
              </Field>
              <Field label="Operational Notes / Incident Details" className="sm:col-span-3">
                <Textarea rows={2} value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="Provide detailed background for audit inspection..." />
              </Field>
            </div>
          </FormSection>

          <FormSection
            title="Adjusted Batches & Quantities"
            description="Specify exact batch and base quantity for each affected product"
            icon={Package}
            badge={`${lines.length} lines`}
          >
            <BatchLinesEditor lines={lines} onChange={setLines} storeId={effectiveStore} />
          </FormSection>

          {create.isError && <InlineError error={create.error} />}
        </div>
      </Drawer>

      <AdjustmentDrawer id={selectedId} onClose={() => setParams({})} />
    </Page>
  )
}

function AdjustmentDrawer({ id, onClose }: { id: string | null; onClose: () => void }) {
  const queryClient = useQueryClient()
  const canApprove = usePermission('stock.adjust.approve')
  const adjustment = useQuery({ queryKey: ['adjustments', id], queryFn: () => apiGet<StockAdjustment>(`/api/inventory/adjustments/${id}`), enabled: !!id })

  const decide = useMutation({
    mutationFn: ({ kind, reason }: { kind: 'approve' | 'reject'; reason?: string }) => apiPost<StockAdjustment>(`/api/inventory/adjustments/${id}/${kind}`, kind === 'reject' ? { reason } : {}),
    onSuccess: (adj) => {
      toast.success(`${adj.doc_number} ${adj.approval_status.toLowerCase()}`)
      queryClient.invalidateQueries({ queryKey: ['adjustments'] })
      queryClient.invalidateQueries({ queryKey: ['inventory'] })
      queryClient.invalidateQueries({ queryKey: ['products'] })
    },
  })

  const a = adjustment.data
  const showCost = !!a?.lines?.some((l) => l.unit_cost !== undefined)
  return (
    <Drawer open={!!id} onClose={onClose} title={a?.doc_number ?? 'Adjustment'} subtitle={a ? `${a.store?.code ?? ''} · ${titleCase(a.reason_code)}` : undefined} width={720}>
      {adjustment.isLoading && <LoadingSkeleton />}
      {adjustment.isError && <InlineError error={adjustment.error} />}
      {a && (
        <div className="space-y-4">
          <div className="flex items-center gap-2"><StatusBadge status={a.approval_status} />{a.total_value !== undefined && <span className="tabular text-sm">Value <MoneyCell value={a.total_value} symbol className="font-bold" /></span>}</div>
          {a.approval_status === 'PENDING' && (
            <ApprovalBar
              title="Awaiting approval"
              message="Above the adjustment value threshold. A second person with stock.adjust.approve must approve or reject."
              canApprove={canApprove}
              isPending={decide.isPending}
              onApprove={() => decide.mutate({ kind: 'approve' })}
              onReject={(reason) => decide.mutate({ kind: 'reject', reason })}
            />
          )}
          <DescriptionList items={[{ label: 'Notes', value: a.notes ?? '—' }, { label: 'Created', value: formatDateTime(a.created_at) }]} />
          <table className="ui-table">
            <thead><tr><th>Product</th><th>Batch</th><th className="text-right">Qty (base)</th>{showCost && <th className="text-right">Unit cost</th>}{showCost && <th className="text-right">Value</th>}</tr></thead>
            <tbody>
              {(a.lines ?? []).map((l) => (
                <tr key={l.id}>
                  <td>{l.product?.name ?? l.product_id.slice(0, 8)}</td>
                  <td className="tabular">{l.batch?.batch_number ?? l.batch_id.slice(0, 8)}{l.batch && <div className="text-xs text-slate-500 tabular">exp {formatDate(l.batch.expiry_date)}</div>}</td>
                  <td className="text-right"><QtyCell value={l.qty_base} className="font-semibold" /></td>
                  {showCost && <td className="text-right"><MoneyCell value={l.unit_cost} /></td>}
                  {showCost && <td className="text-right"><MoneyCell value={l.line_value} /></td>}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </Drawer>
  )
}
