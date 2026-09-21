import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { ConfirmDialog } from '../../components/ui/Modal'
import { MoneyCell, QtyCell } from '../../components/ui/MoneyCell'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { NoAccess } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button } from '../../components/ui/primitives'
import { apiGet, apiPost } from '../../lib/api'
import { formatDate, titleCase } from '../../lib/format'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Paginated, ProductBatch } from '../../lib/types'
import { BatchDrawer } from '../inventory/BatchesPage'
import { ExpiryBadge } from '../inventory/StockOnHandPage'

type Tab = 'QUARANTINED' | 'PENDING_QC'
const TABS: { key: Tab; label: string; hint: string }[] = [
  { key: 'QUARANTINED', label: 'Quarantined', hint: 'Blocked from every sale until released again.' },
  { key: 'PENDING_QC', label: 'Pending QC', hint: 'Received but not yet released; invisible to FEFO.' },
]

/** Part 15.2 — the two holding states. Release and quarantine go through the same confirm step as Batches & Expiry. */
export default function QuarantinePage() {
  const canView = usePermission('stock.view')
  const canQualityRelease = usePermission('quality.release')
  const canQcRelease = usePermission('grn.qc.release')
  const canRelease = canQualityRelease || canQcRelease
  const showCost = usePermission('product.cost.view')
  const queryClient = useQueryClient()
  const [params, setParams] = useSearchParams()
  const tab: Tab = params.get('tab') === 'PENDING_QC' ? 'PENDING_QC' : 'QUARANTINED'
  const selectedId = params.get('batch')
  const [page, setPage] = useState(1)
  const [action, setAction] = useState<{ kind: 'release' | 'quarantine'; batch: ProductBatch } | null>(null)

  const list = useQuery({
    queryKey: ['batches', 'list', tab, '', page],
    queryFn: () => apiGet<Paginated<ProductBatch>>('/api/batches', { status: tab, page, per_page: 100 }),
    enabled: canView,
    placeholderData: (prev) => prev,
  })
  const counts = useQuery({
    queryKey: ['batches', 'counts', 'holding'],
    queryFn: async () => {
      const [q, p] = await Promise.all([
        apiGet<Paginated<ProductBatch>>('/api/batches', { status: 'QUARANTINED', per_page: 1 }),
        apiGet<Paginated<ProductBatch>>('/api/batches', { status: 'PENDING_QC', per_page: 1 }),
      ])
      return { QUARANTINED: q.total, PENDING_QC: p.total }
    },
    enabled: canView,
  })

  const transition = useMutation({
    mutationFn: ({ kind, batch, reason }: { kind: 'release' | 'quarantine'; batch: ProductBatch; reason: string }) =>
      apiPost<ProductBatch>(`/api/batches/${batch.id}/${kind}`, kind === 'release' ? { justification: reason || null } : { reason }),
    onSuccess: (b) => {
      toast.success(`Batch ${b.batch_number} is now ${titleCase(b.status)}`)
      setAction(null)
      queryClient.invalidateQueries({ queryKey: ['batches'] })
      queryClient.invalidateQueries({ queryKey: ['inventory'] })
      queryClient.invalidateQueries({ queryKey: ['products'] })
      queryClient.invalidateQueries({ queryKey: ['dashboard'] })
    },
  })

  const columns: Column<ProductBatch>[] = [
    { key: 'batch', header: 'Batch', render: (b) => <span className="font-semibold font-mono tabular">{b.batch_number}</span>, sortValue: (b) => b.batch_number },
    { key: 'product', header: 'Product', render: (b) => b.product?.name ?? b.product_id.slice(0, 8), sortValue: (b) => b.product?.name ?? '' },
    { key: 'expiry', header: 'Expiry', render: (b) => <ExpiryBadge date={b.expiry_date} />, sortValue: (b) => b.expiry_date },
    { key: 'status', header: 'Status', render: (b) => <StatusBadge status={b.status} /> },
    { key: 'qty', header: 'On hand', align: 'right', render: (b) => <QtyCell value={b.qty_on_hand ?? '0'} />, sortValue: (b) => Number(b.qty_on_hand ?? 0) },
    { key: 'supplier', header: 'Supplier', render: (b) => b.supplier?.name ?? '—' },
    { key: 'received', header: 'Received', render: (b) => <span className="tabular font-mono text-xs">{formatDate(b.manufacture_date)}</span> },
    ...(showCost ? [{ key: 'cost', header: 'Landed cost', align: 'right' as const, render: (b: ProductBatch) => <MoneyCell value={b.landed_unit_cost} /> }] : []),
    ...(canRelease
      ? [{
          key: 'act', header: '', align: 'right' as const,
          render: (b: ProductBatch) => (
            <div className="flex justify-end gap-1" onClick={(e) => e.stopPropagation()}>
              <Button size="sm" variant="success" onClick={() => setAction({ kind: 'release', batch: b })}>Release</Button>
              {b.status === 'PENDING_QC' && <Button size="sm" variant="danger" onClick={() => setAction({ kind: 'quarantine', batch: b })}>Quarantine</Button>}
            </div>
          ),
        }]
      : []),
  ]

  const active = TABS.find((t) => t.key === tab) ?? TABS[0]

  return (
    <Page>
      <PageHeader parent="Quality & Compliance" title="Quarantine" subtitle="Stock in either holding state is never free to sell. Releasing needs the quality release permission and is audited." />
      {!canView ? (
        <div className="ui-card"><NoAccess permission="stock.view" /></div>
      ) : (
        <>
          <div id="tour-quarantine-tabs" className="flex items-center gap-2 mb-3.5">
            {TABS.map((t) => {
              const isSelected = tab === t.key
              return (
                <button
                  key={t.key}
                  type="button"
                  onClick={() => { setParams({ tab: t.key }); setPage(1) }}
                  className={`h-9 px-4 rounded-xl text-xs font-semibold inline-flex items-center gap-2 transition-all select-none ${
                    isSelected
                      ? 'bg-blue-600 text-white shadow-xs shadow-blue-500/25 ring-2 ring-blue-500/20'
                      : 'bg-white hover:bg-slate-50 text-slate-700 hover:text-slate-900 border border-slate-200/80 shadow-2xs'
                  }`}
                >
                  {t.label}
                  <span
                    className={`tabular font-mono text-xs px-2 py-0.5 rounded-full font-bold ${
                      isSelected ? 'bg-blue-700 text-white' : 'bg-slate-100 text-slate-600'
                    }`}
                  >
                    {counts.data?.[t.key] ?? '…'}
                  </span>
                </button>
              )
            })}
          </div>
          <div id="tour-quarantine-table" className="ui-card">
            <div className="px-4 py-2.5 text-xs text-slate-500 border-b border-slate-100 bg-slate-50/50 flex items-center justify-between">
              <span>{active.hint}</span>
              <span className="text-slate-400 font-medium">Click any row to view batch movements & store balances</span>
            </div>
            <DataTable columns={columns} rows={list.data?.data} rowKey={(b) => b.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(b) => setParams({ tab, batch: b.id })} selectedKey={selectedId} emptyTitle={tab === 'QUARANTINED' ? 'Nothing in quarantine' : 'Nothing awaiting QC'} initialSort={{ key: 'expiry', dir: 'asc' }} />
            <Pagination page={list.data} onPage={setPage} />
          </div>
        </>
      )}
      <BatchDrawer id={selectedId} onClose={() => setParams({ tab })} />
      <ConfirmDialog
        open={action !== null}
        title={action?.kind === 'release' ? `Release batch ${action.batch.batch_number}` : `Quarantine batch ${action?.batch.batch_number ?? ''}`}
        message={action?.kind === 'release' ? 'Released stock becomes free to sell (FEFO order).' : 'Quarantined stock is blocked from every sale until released again.'}
        confirmLabel={action?.kind === 'release' ? 'Release' : 'Quarantine'}
        danger={action?.kind === 'quarantine'}
        requireReason={action?.kind === 'quarantine' ? 'Reason' : undefined}
        isPending={transition.isPending}
        onCancel={() => setAction(null)}
        onConfirm={(reason) => action && transition.mutate({ kind: action.kind, batch: action.batch, reason })}
      />
    </Page>
  )
}
