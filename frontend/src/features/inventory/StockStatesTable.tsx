import { DataTable, type Column } from '../../components/ui/DataTable'
import { MoneyCell, QtyCell } from '../../components/ui/MoneyCell'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { dCmp, dIsPos } from '../../lib/decimal'
import type { StockStateRow } from '../../lib/types'
import { ExpiryBadge } from './ExpiryBadge'

export interface StockStatesTableProps {
  rows: StockStateRow[] | undefined
  showCost: boolean
  showProduct?: boolean
  isLoading?: boolean
  error?: unknown
}

export function StockStatesTable({
  rows,
  showCost,
  showProduct = true,
  isLoading,
  error,
}: StockStatesTableProps) {
  const columns: Column<StockStateRow>[] = [
    ...(showProduct
      ? [
          {
            key: 'product',
            header: 'Product',
            render: (r: StockStateRow) => (
              <div className="py-0.5">
                <div className="font-extrabold text-slate-900 text-[13.5px] tracking-tight">{r.product_name}</div>
                <div className="text-[11px] font-semibold text-slate-400 tabular mt-0.5">{r.product_code}</div>
              </div>
            ),
            sortValue: (r: StockStateRow) => r.product_name,
          } satisfies Column<StockStateRow>,
        ]
      : []),
    {
      key: 'store',
      header: 'Store',
      render: (r) => (
        <span className="inline-flex px-2 py-0.5 rounded-lg bg-slate-100 text-slate-700 font-bold text-[11px] tracking-wide">
          {r.store_code}
        </span>
      ),
      sortValue: (r) => r.store_code,
    },
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
      rowKey={(r) => `${r.product_id}-${r.store_id}`}
      isLoading={isLoading}
      error={error}
      emptyTitle="No stock balances match"
    />
  )
}
