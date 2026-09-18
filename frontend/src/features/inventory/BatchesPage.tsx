import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { ConfirmDialog } from '../../components/ui/Modal'
import { MoneyCell, QtyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Card, DescriptionList, Field, Select } from '../../components/ui/primitives'
import { apiGet, apiPost } from '../../lib/api'
import { formatDate, formatDateTime, titleCase } from '../../lib/format'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Paginated, ProductBatch } from '../../lib/types'
import { ExpiryBadge } from './StockOnHandPage'

const STATUSES = ['PENDING_QC', 'RELEASED', 'REJECTED', 'QUARANTINED', 'EXPIRED', 'RECALLED', 'RETURNED_TO_SUPPLIER', 'DISPOSED']

export default function BatchesPage() {
  const [params, setParams] = useSearchParams()
  const showCost = usePermission('product.cost.view')
  const [status, setStatus] = useState('')
  const [within, setWithin] = useState('')
  const [page, setPage] = useState(1)
  const selectedId = params.get('batch')

  const list = useQuery({
    queryKey: ['batches', 'list', status, within, page],
    queryFn: () => apiGet<Paginated<ProductBatch>>('/api/batches', { status, expiring_within_days: within, page, per_page: 100 }),
    placeholderData: (prev) => prev,
  })

  const columns: Column<ProductBatch>[] = [
    { key: 'batch', header: 'Batch', render: (b) => <span className="font-semibold tabular">{b.batch_number}</span>, sortValue: (b) => b.batch_number },
    { key: 'product', header: 'Product', render: (b) => b.product?.name ?? b.product_id.slice(0, 8), sortValue: (b) => b.product?.name ?? '' },
    { key: 'expiry', header: 'Expiry', render: (b) => <ExpiryBadge date={b.expiry_date} />, sortValue: (b) => b.expiry_date },
    { key: 'status', header: 'Status', render: (b) => <StatusBadge status={b.status} /> },
    { key: 'qty', header: 'On hand', align: 'right', render: (b) => <QtyCell value={b.qty_on_hand ?? '0'} />, sortValue: (b) => Number(b.qty_on_hand ?? 0) },
    { key: 'supplier', header: 'Supplier', render: (b) => b.supplier?.name ?? '—' },
    ...(showCost ? [{ key: 'cost', header: 'Landed cost', align: 'right' as const, render: (b: ProductBatch) => <MoneyCell value={b.landed_unit_cost} /> }] : []),
  ]

  return (
    <Page>
      <PageHeader parent="Inventory" title="Batches & Expiry" subtitle="Expiry tiers at 30 / 90 / 180 days. Open a batch for its full trace: movements and recipients." />
      <FilterBar>
        <Field label="Expiring within">
          <div className="flex gap-1">
            {[
              ['', 'Any'],
              ['0', 'Expired'],
              ['30', '30 d'],
              ['90', '90 d'],
              ['180', '180 d'],
            ].map(([v, label]) => (
              <Button key={v} size="sm" variant={within === v ? 'primary' : 'secondary'} onClick={() => { setWithin(v); setPage(1) }}>
                {label}
              </Button>
            ))}
          </div>
        </Field>
        <Field label="Status">
          <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
            <option value="">All</option>
            {STATUSES.map((s) => (
              <option key={s} value={s}>{titleCase(s)}</option>
            ))}
          </Select>
        </Field>
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(b) => b.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(b) => setParams({ batch: b.id })} selectedKey={selectedId} emptyTitle="No batches match" initialSort={{ key: 'expiry', dir: 'asc' }} />
        <Pagination page={list.data} onPage={setPage} />
      </div>
      <BatchDrawer id={selectedId} onClose={() => setParams({})} />
    </Page>
  )
}

export function BatchDrawer({ id, onClose }: { id: string | null; onClose: () => void }) {
  const queryClient = useQueryClient()
  const canRelease = usePermission('quality.release')
  const showCost = usePermission('product.cost.view')
  const [action, setAction] = useState<'release' | 'quarantine' | null>(null)
  const batch = useQuery({ queryKey: ['batches', id], queryFn: () => apiGet<ProductBatch>(`/api/batches/${id}`), enabled: !!id })

  const transition = useMutation({
    mutationFn: ({ kind, reason }: { kind: 'release' | 'quarantine'; reason: string }) =>
      apiPost<ProductBatch>(`/api/batches/${id}/${kind}`, kind === 'release' ? { justification: reason || null } : { reason }),
    onSuccess: (b) => {
      toast.success(`Batch ${b.batch_number} is now ${titleCase(b.status)}`)
      setAction(null)
      queryClient.invalidateQueries({ queryKey: ['batches'] })
      queryClient.invalidateQueries({ queryKey: ['inventory'] })
      queryClient.invalidateQueries({ queryKey: ['products'] })
    },
  })

  const b = batch.data
  const canReleaseNow = b && ['PENDING_QC', 'QUARANTINED'].includes(b.status)
  const canQuarantineNow = b && ['RELEASED', 'PENDING_QC'].includes(b.status)
  return (
    <Drawer
      open={!!id}
      onClose={onClose}
      title={b ? `Batch ${b.batch_number}` : 'Batch'}
      subtitle={b ? `${b.product?.name ?? ''} · expires ${formatDate(b.expiry_date)}` : undefined}
      width={800}
      actions={
        b && canRelease ? (
          <div className="flex gap-2">
            {canReleaseNow && <Button size="sm" variant="success" onClick={() => setAction('release')}>Release</Button>}
            {canQuarantineNow && <Button size="sm" variant="danger" onClick={() => setAction('quarantine')}>Quarantine</Button>}
          </div>
        ) : null
      }
    >
      {batch.isLoading && <LoadingSkeleton />}
      {batch.isError && <InlineError error={batch.error} />}
      {b && (
        <div className="space-y-4">
          <div className="flex items-center gap-2">
            <StatusBadge status={b.status} />
            <ExpiryBadge date={b.expiry_date} />
          </div>
          <DescriptionList
            items={[
              { label: 'Manufactured', value: formatDate(b.manufacture_date) },
              { label: 'Supplier', value: b.supplier?.name ?? '—' },
              { label: 'Manufacturer ref', value: b.manufacturer_batch_ref ?? '—' },
              { label: 'QC released', value: formatDateTime(b.qc_released_at) },
              ...(showCost ? [{ label: 'Unit / landed cost', value: <><MoneyCell value={b.unit_cost} /> / <MoneyCell value={b.landed_unit_cost} /></> }] : []),
              { label: 'Distributed (base)', value: <QtyCell value={b.distributed_base ?? '0'} /> },
            ]}
          />
          <Card title="Balances by store">
            <table className="ui-table">
              <thead>
                <tr><th>Store</th><th className="text-right">On hand</th><th className="text-right">Reserved</th>{showCost && <th className="text-right">WAC</th>}</tr>
              </thead>
              <tbody>
                {(b.balances ?? []).map((bal) => (
                  <tr key={bal.id}>
                    <td>{bal.store?.code ?? bal.store_id.slice(0, 8)}</td>
                    <td className="text-right"><QtyCell value={bal.qty_on_hand} /></td>
                    <td className="text-right"><QtyCell value={bal.qty_reserved} /></td>
                    {showCost && <td className="text-right"><MoneyCell value={bal.wac} /></td>}
                  </tr>
                ))}
              </tbody>
            </table>
          </Card>
          <Card title={`Movements (${b.movements?.length ?? 0})`}>
            <table className="ui-table">
              <thead>
                <tr><th>When</th><th>Type</th><th>Store</th><th className="text-right">Qty</th>{showCost && <th className="text-right">Unit cost</th>}<th>Source</th></tr>
              </thead>
              <tbody>
                {(b.movements ?? []).map((m) => (
                  <tr key={m.id}>
                    <td className="tabular whitespace-nowrap">{formatDateTime(m.txn_datetime)}</td>
                    <td>{titleCase(m.txn_type)}</td>
                    <td>{m.store?.code ?? '—'}</td>
                    <td className="text-right"><QtyCell value={m.qty_base} /></td>
                    {showCost && <td className="text-right"><MoneyCell value={m.unit_cost} /></td>}
                    <td className="text-[var(--text-muted)] tabular">{m.source_doc_type ? `${titleCase(m.source_doc_type)} ${m.source_doc_id?.slice(0, 8) ?? ''}` : '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </Card>
          <Card title={`Recipients (${b.recipients?.length ?? 0})`}>
            {(b.recipients ?? []).length === 0 ? (
              <div className="p-4 text-[12px] text-[var(--text-muted)]">Nothing from this batch has been sold yet.</div>
            ) : (
              <table className="ui-table">
                <thead>
                  <tr><th>Sale</th><th>Posted</th><th>Mode</th><th>Customer</th><th className="text-right">Qty</th></tr>
                </thead>
                <tbody>
                  {(b.recipients ?? []).map((r, i) => (
                    <tr key={`${r.sale_id}-${i}`}>
                      <td className="tabular font-semibold">{r.doc_number}</td>
                      <td className="tabular">{formatDateTime(r.posted_at)}</td>
                      <td><StatusBadge status={r.sale_mode} /></td>
                      <td>{r.customer_name ?? 'Walk-in'}</td>
                      <td className="text-right"><QtyCell value={r.qty_base} />{r.is_bonus && <span className="ml-1 text-[10px] font-bold text-[var(--status-green)]">FREE</span>}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </Card>
        </div>
      )}
      <ConfirmDialog
        open={action !== null}
        title={action === 'release' ? `Release batch ${b?.batch_number ?? ''}` : `Quarantine batch ${b?.batch_number ?? ''}`}
        message={action === 'release' ? 'Released stock becomes free to sell (FEFO order).' : 'Quarantined stock is blocked from every sale until released again.'}
        confirmLabel={action === 'release' ? 'Release' : 'Quarantine'}
        danger={action === 'quarantine'}
        requireReason={action === 'quarantine' ? 'Reason' : undefined}
        isPending={transition.isPending}
        onCancel={() => setAction(null)}
        onConfirm={(reason) => action && transition.mutate({ kind: action, reason })}
      />
    </Drawer>
  )
}
