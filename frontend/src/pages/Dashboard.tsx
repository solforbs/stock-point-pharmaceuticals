import { useQuery } from '@tanstack/react-query'
import { motion } from 'framer-motion'
import { ApprovalsQueueSection } from '../features/dashboard/components/ApprovalsQueueSection'
import { AttentionAlertsSection } from '../features/dashboard/components/AttentionAlertsSection'
import { DashboardHeader } from '../features/dashboard/components/DashboardHeader'
import { DashboardKpis } from '../features/dashboard/components/DashboardKpis'
import { HeroSalesChartCard } from '../features/dashboard/components/HeroSalesChartCard'
import { LatestSalesSection } from '../features/dashboard/components/LatestSalesSection'
import { QuickActionsSection } from '../features/dashboard/components/QuickActionsSection'
import { useCurrentUser } from '../hooks/useCurrentUser'
import { apiGet } from '../lib/api'
import { dSum } from '../lib/decimal'
import { todayIso } from '../lib/format'
import { useDashboardSummary } from '../lib/hooks'
import { usePermission } from '../lib/permissions'
import type { ArAgeing, Paginated, Sale } from '../lib/types'

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
    queryFn: () => apiGet<Paginated<Sale>>('/api/sales', { from: today, to: today, status: 'POSTED', per_page: 50 }),
  })
  const ar = useQuery({
    queryKey: ['finance', 'ar-ageing'],
    queryFn: () => apiGet<ArAgeing>('/api/finance/ar-ageing'),
    enabled: canAr,
  })

  const s = summary.data
  const salesToday = s?.sales_today
  const periodClosed = !!s?.finance && !s.finance.period_open_for_today

  const todayRows = sales.data?.data ?? []
  const todayTotal = dSum(todayRows.map((s) => s.grand_total))

  const pendingQc = s?.inventory?.pending_qc_qty ?? '0.0000'
  const lowStock = s?.inventory?.low_stock_count ?? 0
  const expiringCount = s?.inventory?.expiring_90d_batches ?? 0
  const expiringQty = s?.inventory?.expiring_90d_qty ?? '0.0000'

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
        salesLoading={summary.isLoading}
        voidedCount={salesToday?.voided_today}
        canAr={canAr}
        arTotal={ar.data?.totals.total ?? s?.ar?.total ?? '0.0000'}
        arCustomersCount={ar.data?.data.length ?? s?.ar?.customers_count ?? 0}
        arD90Plus={ar.data?.totals.d90_plus ?? s?.ar?.d90_plus ?? '0.0000'}
        arLoading={summary.isLoading}
        expiringCount={expiringCount}
        expiringQty={expiringQty}
        expiringLoading={summary.isLoading}
        pendingQcQty={pendingQc}
        lowStockCount={lowStock}
        stockLoading={summary.isLoading}
      />

      <div className="grid gap-6 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <HeroSalesChartCard salesToday={salesToday} salesList={todayRows} />
        </div>
        <div>
          <QuickActionsSection />
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
