import { useQuery } from '@tanstack/react-query'
import { AlertTriangle, Boxes, ShoppingCart, Wallet } from 'lucide-react'
import { Link } from 'react-router-dom'
import { useCurrentUser } from '../hooks/useCurrentUser'
import { apiGet } from '../lib/api'
import { dCmp, dIsPos, dSum } from '../lib/decimal'
import { todayIso } from '../lib/format'
import { formatKes, formatQty } from '../lib/money'
import { usePermission } from '../lib/permissions'
import type { ArAgeing, Paginated, ProductBatch, Sale, StockStateRow } from '../lib/types'

function Kpi({ icon: Icon, label, value, hint, to, tone }: { icon: typeof Boxes; label: string; value: string; hint?: string; to: string; tone?: string }) {
  return (
    <Link to={to} className="ui-card p-4 flex items-start gap-3 hover:border-[var(--color-navy)]">
      <div className="p-2 rounded-lg" style={{ background: `color-mix(in srgb, ${tone ?? 'var(--color-navy)'} 12%, transparent)`, color: tone ?? 'var(--color-navy)' }}>
        <Icon size={18} />
      </div>
      <div className="min-w-0">
        <div className="ui-label !mb-0.5">{label}</div>
        <div className="text-[22px] font-extrabold tabular leading-tight">{value}</div>
        {hint && <div className="text-[11px] text-[var(--text-muted)] mt-0.5">{hint}</div>}
      </div>
    </Link>
  )
}

export default function Dashboard() {
  const { data: user } = useCurrentUser()
  const canAr = usePermission('finance.ar.view')
  const today = todayIso()

  const sales = useQuery({ queryKey: ['sales', 'today', today], queryFn: () => apiGet<Paginated<Sale>>('/api/sales', { from: today, to: today, status: 'POSTED', per_page: 200 }) })
  const ar = useQuery({ queryKey: ['finance', 'ar-ageing'], queryFn: () => apiGet<ArAgeing>('/api/finance/ar-ageing'), enabled: canAr })
  const expiring = useQuery({ queryKey: ['batches', 'expiring', 90], queryFn: () => apiGet<Paginated<ProductBatch>>('/api/batches', { expiring_within_days: 90, per_page: 200 }) })
  const stock = useQuery({ queryKey: ['inventory', 'stock', {}], queryFn: () => apiGet<{ data: StockStateRow[] }>('/api/inventory/stock') })

  const todayRows = sales.data?.data ?? []
  const todayTotal = dSum(todayRows.map((s) => s.grand_total))
  const stockRows = stock.data?.data ?? []
  const pendingQc = dSum(stockRows.map((r) => r.pending_qc))
  const lowStock = stockRows.filter((r) => dIsPos(r.reorder_point) && dCmp(r.free_to_sell, r.reorder_point) < 0).length
  const expiringSoon = (expiring.data?.data ?? []).filter((b) => Number(b.qty_on_hand ?? 0) > 0)
  const hour = new Date().getHours()
  const greeting = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening'

  return (
    <div className="p-7">
      <h1 className="text-[19px] font-extrabold text-[var(--text)]">
        {greeting}, {user?.name ?? '...'}
      </h1>
      <p className="text-[11px] text-[var(--text-muted)] mt-1 mb-5">
        {user?.active_branch ? `${user.active_branch.code} · ${user.active_branch.name}` : ''} · {today}
      </p>

      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <Kpi icon={ShoppingCart} label="Sales today" value={sales.isLoading ? '…' : formatKes(todayTotal)} hint={`${todayRows.length} posted sale${todayRows.length === 1 ? '' : 's'}`} to="/sell/invoices" />
        {canAr && <Kpi icon={Wallet} label="Receivables outstanding" value={ar.isLoading ? '…' : formatKes(ar.data?.totals.total ?? '0')} hint={`${ar.data?.data.length ?? 0} customers with a balance · 90+ days ${formatKes(ar.data?.totals.d90_plus ?? '0')}`} to="/finance/receivables" tone="var(--status-amber)" />}
        <Kpi icon={AlertTriangle} label="Batches expiring ≤ 90 days" value={expiring.isLoading ? '…' : String(expiringSoon.length)} hint={`${formatQty(dSum(expiringSoon.map((b) => b.qty_on_hand ?? '0')))} base units at risk`} to="/inventory/batches" tone="var(--status-red)" />
        <Kpi icon={Boxes} label="Stock pending QC" value={stock.isLoading ? '…' : formatQty(pendingQc)} hint={`${lowStock} product/store line${lowStock === 1 ? '' : 's'} below reorder point`} to="/inventory/stock-on-hand" tone="var(--status-blue)" />
      </div>

      <div className="grid gap-4 lg:grid-cols-2 mt-6">
        <section className="ui-card">
          <header className="px-4 py-2.5 border-b border-[var(--border)] text-[13px] font-bold">Latest sales today</header>
          {todayRows.length === 0 ? (
            <div className="p-4 text-[12px] text-[var(--text-muted)]">Nothing posted yet today.</div>
          ) : (
            <table className="ui-table">
              <tbody>
                {todayRows.slice(0, 8).map((s) => (
                  <tr key={s.id}>
                    <td className="tabular font-semibold"><Link to={`/sell/invoices?sale=${s.id}`} className="hover:underline">{s.doc_number}</Link></td>
                    <td>{s.sale_mode}</td>
                    <td>{s.customer?.name ?? 'Walk-in'}</td>
                    <td className="text-right tabular">{formatKes(s.grand_total)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </section>
        <section className="ui-card">
          <header className="px-4 py-2.5 border-b border-[var(--border)] text-[13px] font-bold">Quick actions</header>
          <div className="p-4 flex flex-wrap gap-2 text-[12px]">
            {[
              ['/sell/pos', 'Open POS'],
              ['/buy/goods-receipts', 'Receive goods'],
              ['/inventory/batches', 'Release QC batches'],
              ['/sell/quotations', 'New quotation'],
              ['/warehouse/pick-lists', 'Pick orders'],
              ['/finance/receivables', 'Receive payment'],
            ].map(([to, label]) => (
              <Link key={to} to={to} className="px-3 py-1.5 rounded-md border border-[var(--border-strong)] hover:bg-[var(--surface-2)] font-semibold">{label}</Link>
            ))}
          </div>
        </section>
      </div>
    </div>
  )
}
