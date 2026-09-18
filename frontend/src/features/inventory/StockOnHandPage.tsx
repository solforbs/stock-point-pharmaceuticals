import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { useDebounced } from '../../components/ProductSearch'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { MoneyCell, QtyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Field, Input, Select } from '../../components/ui/primitives'
import { apiGet } from '../../lib/api'
import { dCmp, dIsPos } from '../../lib/decimal'
import { expiryTier, formatDate } from '../../lib/format'
import { useStores } from '../../lib/hooks'
import { usePermission } from '../../lib/permissions'
import type { StockStateRow } from '../../lib/types'

export function ExpiryBadge({ date }: { date: string | null | undefined }) {
  if (!date) return <span className="text-[var(--text-muted)]">—</span>
  const tier = expiryTier(date)
  const tone = tier === 'expired' ? 'red' : tier === 'd30' ? 'red' : tier === 'd90' ? 'amber' : tier === 'd180' ? 'amber' : 'green'
  const label = tier === 'expired' ? 'Expired' : tier === 'd30' ? '≤ 30 d' : tier === 'd90' ? '≤ 90 d' : tier === 'd180' ? '≤ 180 d' : 'OK'
  return (
    <span className="inline-flex items-center gap-1.5 tabular whitespace-nowrap">
      {formatDate(date)}
      <StatusBadge status={label} tone={tone} label={label} />
    </span>
  )
}

/** Part 7.3 — the eight quantity states per product per store. */
export function StockStatesTable({ rows, showCost, showProduct = true, isLoading, error }: { rows: StockStateRow[] | undefined; showCost: boolean; showProduct?: boolean; isLoading?: boolean; error?: unknown }) {
  const columns: Column<StockStateRow>[] = [
    ...(showProduct
      ? [{ key: 'product', header: 'Product', render: (r: StockStateRow) => <><div className="font-semibold">{r.product_name}</div><div className="text-[10.5px] text-[var(--text-muted)]">{r.product_code}</div></>, sortValue: (r: StockStateRow) => r.product_name } satisfies Column<StockStateRow>]
      : []),
    { key: 'store', header: 'Store', render: (r) => r.store_code, sortValue: (r) => r.store_code },
    { key: 'on_hand', header: 'On hand', align: 'right', render: (r) => <QtyCell value={r.on_hand} />, sortValue: (r) => Number(r.on_hand) },
    { key: 'reserved', header: 'Reserved', align: 'right', render: (r) => <QtyCell value={r.reserved} />, sortValue: (r) => Number(r.reserved) },
    {
      key: 'free',
      header: 'Free to sell',
      align: 'right',
      render: (r) => {
        const low = dIsPos(r.reorder_point) && dCmp(r.free_to_sell, r.reorder_point) < 0
        return (
          <span className="inline-flex items-center gap-1.5">
            <QtyCell value={r.free_to_sell} className={`font-bold ${!dIsPos(r.free_to_sell) ? 'text-[var(--status-red)]' : ''}`} />
            {!dIsPos(r.free_to_sell) ? <StatusBadge status="OUT_OF_STOCK" label="Out" /> : low ? <StatusBadge status="LOW" label="Low" /> : null}
          </span>
        )
      },
      sortValue: (r) => Number(r.free_to_sell),
    },
    { key: 'pending_qc', header: 'Pending QC', align: 'right', render: (r) => <QtyCell value={r.pending_qc} />, sortValue: (r) => Number(r.pending_qc) },
    { key: 'quarantined', header: 'Quarantined', align: 'right', render: (r) => <QtyCell value={r.quarantined} className={dIsPos(r.quarantined) ? 'text-[var(--status-purple)]' : ''} />, sortValue: (r) => Number(r.quarantined) },
    { key: 'expired', header: 'Expired', align: 'right', render: (r) => <QtyCell value={r.expired} className={dIsPos(r.expired) ? 'text-[var(--status-red)]' : ''} />, sortValue: (r) => Number(r.expired) },
    { key: 'recalled', header: 'Recalled', align: 'right', render: (r) => <QtyCell value={r.recalled} className={dIsPos(r.recalled) ? 'text-[var(--status-purple)]' : ''} />, sortValue: (r) => Number(r.recalled) },
    { key: 'in_transit', header: 'In transit', align: 'right', render: (r) => <QtyCell value={r.in_transit} className={dIsPos(r.in_transit) ? 'text-[var(--status-blue)]' : ''} />, sortValue: (r) => Number(r.in_transit) },
    { key: 'on_order', header: 'On order', align: 'right', render: (r) => <QtyCell value={r.on_order} />, sortValue: (r) => Number(r.on_order) },
    { key: 'expiry', header: 'Nearest expiry', render: (r) => <ExpiryBadge date={r.nearest_expiry} />, sortValue: (r) => r.nearest_expiry ?? '' },
    ...(showCost ? [{ key: 'value', header: 'Value at cost', align: 'right' as const, render: (r: StockStateRow) => <MoneyCell value={r.value_at_cost} />, sortValue: (r: StockStateRow) => Number(r.value_at_cost ?? 0) }] : []),
  ]

  return (
    <DataTable
      columns={columns}
      rows={rows}
      rowKey={(r) => `${r.product_id}|${r.store_id}`}
      isLoading={isLoading}
      error={error}
      emptyTitle="No stock balances"
      emptyHint="Stock appears here once a goods receipt posts."
      renderExpanded={(r) => (
        <table className="ui-table">
          <thead>
            <tr>
              <th>Batch</th>
              <th>Expiry</th>
              <th>Status</th>
              <th className="text-right">On hand</th>
              <th className="text-right">Reserved</th>
              {showCost && <th className="text-right">WAC</th>}
            </tr>
          </thead>
          <tbody>
            {r.batches.map((b) => (
              <tr key={b.batch_id}>
                <td className="tabular font-semibold">{b.batch_number}</td>
                <td><ExpiryBadge date={b.expiry_date} /></td>
                <td><StatusBadge status={b.status} /></td>
                <td className="text-right"><QtyCell value={b.on_hand} /></td>
                <td className="text-right"><QtyCell value={b.reserved} /></td>
                {showCost && <td className="text-right"><MoneyCell value={b.wac} /></td>}
              </tr>
            ))}
          </tbody>
        </table>
      )}
    />
  )
}

export default function StockOnHandPage() {
  const showCost = usePermission('product.cost.view')
  const stores = useStores()
  const [q, setQ] = useState('')
  const [storeId, setStoreId] = useState('')
  const dq = useDebounced(q, 250)

  const stock = useQuery({
    queryKey: ['inventory', 'stock', { q: dq, store_id: storeId }],
    queryFn: () => apiGet<{ data: StockStateRow[] }>('/api/inventory/stock', { q: dq, store_id: storeId }),
    placeholderData: (prev) => prev,
  })

  return (
    <Page>
      <PageHeader parent="Inventory" title="Stock on Hand" subtitle="The eight quantity states, computed from the batch register. Expand a row for its batches." />
      <FilterBar>
        <Field label="Search" className="w-72">
          <Input placeholder="Product name or code" value={q} onChange={(e) => setQ(e.target.value)} />
        </Field>
        <Field label="Store">
          <Select value={storeId} onChange={(e) => setStoreId(e.target.value)}>
            <option value="">All stores</option>
            {(stores.data ?? []).map((s) => (
              <option key={s.id} value={s.id}>{s.code} · {s.name}</option>
            ))}
          </Select>
        </Field>
      </FilterBar>
      <div className="ui-card">
        <StockStatesTable rows={stock.data?.data} showCost={showCost} isLoading={stock.isLoading} error={stock.error} />
      </div>
    </Page>
  )
}
