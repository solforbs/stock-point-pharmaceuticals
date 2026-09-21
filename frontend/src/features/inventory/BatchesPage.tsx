import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { MoneyCell, QtyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Field, Select } from '../../components/ui/primitives'
import { apiGet } from '../../lib/api'
import { titleCase } from '../../lib/format'
import { usePermission } from '../../lib/permissions'
import type { Paginated, ProductBatch } from '../../lib/types'
import { BatchDrawer } from './BatchDrawer'
import { ExpiryBadge } from './ExpiryBadge'

export { BatchDrawer }

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
    { key: 'batch', header: 'Batch', render: (b) => <span className="font-semibold tabular text-blue-600 hover:text-blue-700">{b.batch_number}</span>, sortValue: (b) => b.batch_number },
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
      <div id="tour-batches-filters">
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
      </div>
      <div id="tour-batches-table" className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(b) => b.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(b) => setParams({ batch: b.id })} selectedKey={selectedId} emptyTitle="No batches match" initialSort={{ key: 'expiry', dir: 'asc' }} />
        <Pagination page={list.data} onPage={setPage} />
      </div>
      <BatchDrawer id={selectedId} onClose={() => setParams({})} />
    </Page>
  )
}
