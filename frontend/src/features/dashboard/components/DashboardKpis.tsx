import { AlertTriangle, Boxes, ShoppingCart, Wallet } from 'lucide-react'
import { StatCard } from '../../../components/ui/StatCard'
import { formatKes, formatQty } from '../../../lib/money'

export interface DashboardKpisProps {
  salesTotal: string
  salesCount: number
  salesLoading?: boolean
  voidedCount?: number
  canAr?: boolean
  arTotal?: string
  arCustomersCount?: number
  arD90Plus?: string
  arLoading?: boolean
  expiringCount: number
  expiringQty: string
  expiringLoading?: boolean
  pendingQcQty: string
  lowStockCount: number
  stockLoading?: boolean
}

export function DashboardKpis({
  salesTotal,
  salesCount,
  salesLoading = false,
  voidedCount = 0,
  canAr = false,
  arTotal = '0',
  arCustomersCount = 0,
  arD90Plus = '0',
  arLoading = false,
  expiringCount,
  expiringQty,
  expiringLoading = false,
  pendingQcQty,
  lowStockCount,
  stockLoading = false,
}: DashboardKpisProps) {
  return (
    <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <StatCard
        icon={ShoppingCart}
        label="Sales Today"
        value={formatKes(salesTotal)}
        hint={
          <div className="space-y-0.5">
            <div>{salesCount} posted sale{salesCount === 1 ? '' : 's'}</div>
            {voidedCount > 0 && (
              <span className="text-rose-600 dark:text-rose-400 font-semibold">
                {voidedCount} voided today
              </span>
            )}
          </div>
        }
        to="/sell/invoices"
        tone="var(--color-navy)"
        isLoading={salesLoading}
      />

      {canAr && (
        <StatCard
          icon={Wallet}
          label="Receivables Outstanding"
          value={formatKes(arTotal)}
          hint={
            <span>
              {arCustomersCount} customer{arCustomersCount === 1 ? '' : 's'} · 90d+ {formatKes(arD90Plus)}
            </span>
          }
          to="/finance/receivables"
          tone="#b45309"
          isLoading={arLoading}
        />
      )}

      <StatCard
        icon={AlertTriangle}
        label="Batches Expiring ≤ 90d"
        value={String(expiringCount)}
        hint={<span>{formatQty(expiringQty)} base units at risk</span>}
        to="/inventory/batches?status=NEAR_EXPIRY"
        tone="var(--status-red)"
        isLoading={expiringLoading}
      />

      <StatCard
        icon={Boxes}
        label="Stock Pending QC"
        value={formatQty(pendingQcQty)}
        hint={
          <span>
            {lowStockCount} product line{lowStockCount === 1 ? '' : 's'} below reorder point
          </span>
        }
        to="/inventory/stock-on-hand"
        tone="var(--status-blue)"
        isLoading={stockLoading}
      />
    </section>
  )
}
