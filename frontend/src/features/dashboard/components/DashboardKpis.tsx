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
        hint={`${salesCount} posted sale${salesCount === 1 ? '' : 's'}${voidedCount > 0 ? ` · ${voidedCount} voided` : ''}`}
        trend={voidedCount > 0 ? { value: `${voidedCount} voided`, isPositive: false } : undefined}
        to="/sell/invoices"
        tone="#2563eb"
        isLoading={salesLoading}
      />

      {canAr && (
        <StatCard
          icon={Wallet}
          label="Receivables Outstanding"
          value={formatKes(arTotal)}
          hint={`90d+ balance: ${formatKes(arD90Plus)}`}
          trend={arCustomersCount > 0 ? { value: `${arCustomersCount} accounts`, isPositive: true } : undefined}
          to="/finance/receivables"
          tone="#059669"
          isLoading={arLoading}
        />
      )}

      <StatCard
        icon={AlertTriangle}
        label="Batches Expiring ≤ 90d"
        value={`${expiringCount} Batches`}
        hint={`${formatQty(expiringQty)} base units at risk`}
        trend={expiringCount > 0 ? { value: 'Near Expiry', isPositive: false } : undefined}
        to="/inventory/batches?status=NEAR_EXPIRY"
        tone="#ef4444"
        isLoading={expiringLoading}
      />

      <StatCard
        icon={Boxes}
        label="Stock Pending QC"
        value={`${formatQty(pendingQcQty)} Units`}
        hint={`${lowStockCount} line${lowStockCount === 1 ? '' : 's'} below reorder threshold`}
        to="/inventory/stock-on-hand"
        tone="#8b5cf6"
        isLoading={stockLoading}
      />
    </section>
  )
}
