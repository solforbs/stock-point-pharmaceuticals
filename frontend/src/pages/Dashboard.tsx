import { useQuery } from '@tanstack/react-query'
import { motion } from 'framer-motion'
import { ApprovalsQueueSection } from '../features/dashboard/components/ApprovalsQueueSection'
import { AttentionAlertsSection } from '../features/dashboard/components/AttentionAlertsSection'
import { DashboardHeader } from '../features/dashboard/components/DashboardHeader'
import { DashboardKpis } from '../features/dashboard/components/DashboardKpis'
import { HeroSalesChartCard } from '../features/dashboard/components/HeroSalesChartCard'
import { LatestSalesSection } from '../features/dashboard/components/LatestSalesSection'
import { SalesActivityCard } from '../features/dashboard/components/SalesActivityCard'
import { useCurrentUser } from '../hooks/useCurrentUser'
import { apiGet } from '../lib/api'
import { dCmp, dIsPos, dSum } from '../lib/decimal'
import { todayIso } from '../lib/format'
import { useDashboardSummary } from '../lib/hooks'
import { usePermission } from '../lib/permissions'
import type { ArAgeing, Paginated, ProductBatch, Sale, StockStateRow } from '../lib/types'

const containerVariants = {
  hidden: { opacity: 0, y: 8 },
  visible: {
    opacity: 1,
    y: 0,
    transition: { duration: 0.35, staggerChildren: 0.08 },
  },
}

export default function Dashboard() {
  const { data: user } = useCurrentUser()
  const canAr = usePermission('finance.ar.view')
  const today = todayIso()

  const summary = useDashboardSummary()
  const sales = useQuery({
    queryKey: ['sales', 'today', today],
    queryFn: () => apiGet<Paginated<Sale>>('/api/sales', { from: today, to: today, status: 'POSTED', per_page: 200 }),
  })
  const ar = useQuery({
    queryKey: ['finance', 'ar-ageing'],
    queryFn: () => apiGet<ArAgeing>('/api/finance/ar-ageing'),
    enabled: canAr,
  })
  const expiring = useQuery({
    queryKey: ['batches', 'expiring', 90],
    queryFn: () => apiGet<Paginated<ProductBatch>>('/api/batches', { expiring_within_days: 90, per_page: 200 }),
  })
  const stock = useQuery({
    queryKey: ['inventory', 'stock', {}],
    queryFn: () => apiGet<{ data: StockStateRow[] }>('/api/inventory/stock'),
  })

  const todayRows = sales.data?.data ?? []
  const todayTotal = dSum(todayRows.map((s) => s.grand_total))
  const stockRows = stock.data?.data ?? []
  const pendingQc = dSum(stockRows.map((r) => r.pending_qc))
  const lowStock = stockRows.filter((r) => dIsPos(r.reorder_point) && dCmp(r.free_to_sell, r.reorder_point) < 0).length
  const expiringSoon = (expiring.data?.data ?? []).filter((b) => Number(b.qty_on_hand ?? 0) > 0)
  const expiringQty = dSum(expiringSoon.map((b) => b.qty_on_hand ?? '0'))

  const s = summary.data
  const salesToday = s?.sales_today
  const periodClosed = !!s?.finance && !s.finance.period_open_for_today

  return (
    <motion.div
      variants={containerVariants}
      initial="hidden"
      animate="visible"
      className="p-6 md:p-8 space-y-6 max-w-[1600px] mx-auto"
    >
      <DashboardHeader
        userName={user?.name}
        branchCode={user?.active_branch?.code}
        branchName={user?.active_branch?.name}
        dateIso={today}
        periodClosed={periodClosed}
      />

      <DashboardKpis
        salesTotal={salesToday?.total ?? todayTotal}
        salesCount={salesToday?.count ?? todayRows.length}
        salesLoading={sales.isLoading}
        voidedCount={salesToday?.voided_today}
        canAr={canAr}
        arTotal={ar.data?.totals.total ?? '0'}
        arCustomersCount={ar.data?.data.length ?? 0}
        arD90Plus={ar.data?.totals.d90_plus ?? '0'}
        arLoading={ar.isLoading}
        expiringCount={expiringSoon.length}
        expiringQty={expiringQty}
        expiringLoading={expiring.isLoading}
        pendingQcQty={pendingQc}
        lowStockCount={lowStock}
        stockLoading={stock.isLoading}
      />

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <HeroSalesChartCard salesToday={salesToday} salesList={todayRows} />
        </div>
        <div>
          <SalesActivityCard />
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-2">
        <ApprovalsQueueSection approvals={s?.approvals} />
        <AttentionAlertsSection summary={s} />
      </div>

      <LatestSalesSection sales={todayRows} isLoading={sales.isLoading} />
    </motion.div>
  )
}
