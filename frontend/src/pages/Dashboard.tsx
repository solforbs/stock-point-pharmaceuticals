import { useQuery } from '@tanstack/react-query'
import { AlertTriangle, Boxes, ClipboardCheck, ShoppingCart, Wallet } from 'lucide-react'
import { Link } from 'react-router-dom'
import { useCurrentUser } from '../hooks/useCurrentUser'
import { apiGet } from '../lib/api'
import { dCmp, dIsPos, dSum } from '../lib/decimal'
import { formatDate, todayIso } from '../lib/format'
import { useDashboardSummary } from '../lib/hooks'
import { formatKes, formatQty } from '../lib/money'
import { usePermission } from '../lib/permissions'
import type { ApprovalQueueKey, ArAgeing, DashboardSummary, Paginated, ProductBatch, Sale, SaleMode, StockStateRow } from '../lib/types'

function Kpi({ icon: Icon, label, value, hint, to, tone }: { icon: typeof Boxes; label: string; value: string; hint?: React.ReactNode; to: string; tone?: string }) {
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

const APPROVAL_QUEUES: { key: ApprovalQueueKey; label: string; to: string }[] = [
  { key: 'requisitions', label: 'Requisitions', to: '/buy/requisitions' },
  { key: 'purchase_orders', label: 'Purchase orders', to: '/buy/purchase-orders' },
  { key: 'adjustments', label: 'Stock adjustments', to: '/inventory/adjustments' },
  { key: 'transfers', label: 'Transfers', to: '/inventory/transfers' },
  { key: 'counts', label: 'Stock counts', to: '/inventory/counts' },
  { key: 'customer_returns', label: 'Customer returns', to: '/sell/returns' },
]

const SALE_MODES: SaleMode[] = ['RETAIL', 'WHOLESALE', 'DISPENSING']

type Attention = { key: string; label: string; count: number; to: string; tone: 'red' | 'amber' | 'blue' | 'purple' }

/** Part 16.3 — everything that needs a person to act, in one list, each line linking to the screen that clears it. */
function attentionItems(summary: DashboardSummary): Attention[] {
  const items: Attention[] = []
  const inv = summary.inventory
  if (inv) {
    if (inv.expired_batches_on_hand > 0) items.push({ key: 'expired', label: 'Expired batches still on hand', count: inv.expired_batches_on_hand, to: '/inventory/batches?status=EXPIRED', tone: 'red' })
    if (inv.quarantined_batches > 0) items.push({ key: 'quarantined', label: 'Batches in quarantine', count: inv.quarantined_batches, to: '/quality/quarantine', tone: 'purple' })
    if (inv.transfers_in_transit > 0) items.push({ key: 'transit', label: 'Transfers in transit', count: inv.transfers_in_transit, to: '/inventory/transfers', tone: 'blue' })
  }
  const proc = summary.procurement
  if (proc) {
    if (proc.suppliers_licence_expired > 0) items.push({ key: 'lic-expired', label: 'Suppliers with an expired licence', count: proc.suppliers_licence_expired, to: '/buy/suppliers', tone: 'red' })
    if (proc.suppliers_licence_expiring_30d > 0) items.push({ key: 'lic-expiring', label: 'Supplier licences expiring within 30 days', count: proc.suppliers_licence_expiring_30d, to: '/buy/suppliers', tone: 'amber' })
  }
  if (summary.compliance && summary.compliance.etims_failed > 0) {
    items.push({ key: 'etims', label: 'eTIMS submissions failed', count: summary.compliance.etims_failed, to: '/finance/tax-centre', tone: 'red' })
  }
  return items
}

const toneColor: Record<Attention['tone'], string> = { red: 'var(--status-red)', amber: '#b45309', blue: 'var(--status-blue)', purple: 'var(--status-purple)' }

export default function Dashboard() {
  const { data: user } = useCurrentUser()
  const canAr = usePermission('finance.ar.view')
  const today = todayIso()

  const summary = useDashboardSummary()
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

  const s = summary.data
  const salesToday = s?.sales_today ?? null
  const byMode = salesToday ? SALE_MODES.filter((m) => (salesToday.by_mode[m]?.count ?? 0) > 0) : []
  const approvals = s?.approvals ? APPROVAL_QUEUES.filter((q) => (s.approvals?.[q.key] ?? 0) > 0) : []
  const approvalsTotal = approvals.reduce((n, q) => n + (s?.approvals?.[q.key] ?? 0), 0)
  const attention = s ? attentionItems(s) : []
  const periodClosed = !!s?.finance && !s.finance.period_open_for_today
  const showAttention = !!s && (attention.length > 0 || periodClosed || !!s.inventory || !!s.procurement || !!s.compliance || !!s.finance)

  return (
    <div className="p-7">
      <h1 className="text-[19px] font-extrabold text-[var(--text)]">
        {greeting}, {user?.name ?? '...'}
      </h1>
      <p className="text-[11px] text-[var(--text-muted)] mt-1 mb-5">
        {user?.active_branch ? `${user.active_branch.code} · ${user.active_branch.name}` : ''} · {today}
      </p>

      {periodClosed && (
        <Link to="/finance/periods" className="flex items-center gap-2 mb-4 px-3 py-2 rounded-md border border-[var(--status-red)] bg-[color-mix(in_srgb,var(--status-red)_8%,transparent)] text-[12px] text-[var(--text)] hover:brightness-95">
          <AlertTriangle size={14} className="text-[var(--status-red)] shrink-0" />
          <span><b>No financial period is open for today.</b> Nothing can post until a period covering {formatDate(today)} is opened (Part 12.4). Open Periods →</span>
        </Link>
      )}

      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <Kpi
          icon={ShoppingCart}
          label="Sales today"
          value={sales.isLoading ? '…' : formatKes(salesToday?.total ?? todayTotal)}
          hint={
            <>
              {salesToday?.count ?? todayRows.length} posted sale{(salesToday?.count ?? todayRows.length) === 1 ? '' : 's'}
              {byMode.length > 0 && (
                <span className="block tabular">
                  {byMode.map((m) => `${m.charAt(0)}${m.slice(1).toLowerCase()} ${salesToday?.by_mode[m]?.count ?? 0} · ${formatKes(salesToday?.by_mode[m]?.total ?? '0')}`).join(' | ')}
                </span>
              )}
              {!!salesToday?.voided_today && <span className="block text-[var(--status-red)]">{salesToday.voided_today} voided today</span>}
            </>
          }
          to="/sell/invoices"
        />
        {canAr && <Kpi icon={Wallet} label="Receivables outstanding" value={ar.isLoading ? '…' : formatKes(ar.data?.totals.total ?? '0')} hint={`${ar.data?.data.length ?? 0} customers with a balance · 90+ days ${formatKes(ar.data?.totals.d90_plus ?? '0')}`} to="/finance/receivables" tone="var(--status-amber)" />}
        <Kpi icon={AlertTriangle} label="Batches expiring ≤ 90 days" value={expiring.isLoading ? '…' : String(expiringSoon.length)} hint={`${formatQty(dSum(expiringSoon.map((b) => b.qty_on_hand ?? '0')))} base units at risk`} to="/inventory/batches" tone="var(--status-red)" />
        <Kpi icon={Boxes} label="Stock pending QC" value={stock.isLoading ? '…' : formatQty(pendingQc)} hint={`${lowStock} product/store line${lowStock === 1 ? '' : 's'} below reorder point`} to="/inventory/stock-on-hand" tone="var(--status-blue)" />
      </div>

      {(s?.approvals || showAttention) && (
        <div className="grid gap-4 lg:grid-cols-2 mt-6">
          {s?.approvals && (
            <section className="ui-card">
              <header className="px-4 py-2.5 border-b border-[var(--border)] text-[13px] font-bold flex items-center gap-2">
                <ClipboardCheck size={14} className="text-[var(--color-navy)]" /> Approvals waiting
                <span className="ml-auto tabular text-[11px] font-semibold text-[var(--text-muted)]">{approvalsTotal}</span>
              </header>
              {approvals.length === 0 ? (
                <div className="p-4 text-[12px] text-[var(--text-muted)]">Nothing is waiting for your approval.</div>
              ) : (
                <ul className="divide-y divide-[var(--border)]">
                  {approvals.map((q) => (
                    <li key={q.key}>
                      <Link to={q.to} className="flex items-center justify-between px-4 py-2 text-[12.5px] hover:bg-[var(--surface-2)]">
                        <span>{q.label}</span>
                        <span className="tabular font-bold px-2 py-0.5 rounded-full text-[11px]" style={{ color: '#b45309', background: 'color-mix(in srgb, var(--status-amber) 18%, transparent)' }}>{s.approvals?.[q.key]}</span>
                      </Link>
                    </li>
                  ))}
                </ul>
              )}
            </section>
          )}
          {showAttention && (
            <section className="ui-card">
              <header className="px-4 py-2.5 border-b border-[var(--border)] text-[13px] font-bold flex items-center gap-2">
                <AlertTriangle size={14} className="text-[var(--status-red)]" /> Attention
              </header>
              {attention.length === 0 && !periodClosed ? (
                <div className="p-4 text-[12px] text-[var(--text-muted)]">Nothing needs attention right now.</div>
              ) : (
                <ul className="divide-y divide-[var(--border)]">
                  {periodClosed && (
                    <li>
                      <Link to="/finance/periods" className="flex items-center justify-between px-4 py-2 text-[12.5px] font-semibold text-[var(--status-red)] hover:bg-[var(--surface-2)]">
                        <span>No financial period open for today</span>
                        <span className="text-[11px] uppercase">Open periods</span>
                      </Link>
                    </li>
                  )}
                  {attention.map((a) => (
                    <li key={a.key}>
                      <Link to={a.to} className="flex items-center justify-between px-4 py-2 text-[12.5px] hover:bg-[var(--surface-2)]">
                        <span>{a.label}</span>
                        <span className="tabular font-bold px-2 py-0.5 rounded-full text-[11px]" style={{ color: toneColor[a.tone], background: `color-mix(in srgb, ${toneColor[a.tone]} 14%, transparent)` }}>{a.count}</span>
                      </Link>
                    </li>
                  ))}
                </ul>
              )}
              {s?.finance?.open_period && (
                <div className="px-4 py-2 border-t border-[var(--border)] text-[11px] text-[var(--text-muted)] tabular">
                  Open period {s.finance.open_period.fiscal_year}/{String(s.finance.open_period.period_no).padStart(2, '0')} · {formatDate(s.finance.open_period.start_date)} – {formatDate(s.finance.open_period.end_date)}
                </div>
              )}
            </section>
          )}
        </div>
      )}

      <div className="grid gap-4 lg:grid-cols-2 mt-6">
        <section className="ui-card">
          <header className="px-4 py-2.5 border-b border-[var(--border)] text-[13px] font-bold">Latest sales today</header>
          {todayRows.length === 0 ? (
            <div className="p-4 text-[12px] text-[var(--text-muted)]">Nothing posted yet today.</div>
          ) : (
            <table className="ui-table">
              <tbody>
                {todayRows.slice(0, 8).map((sale) => (
                  <tr key={sale.id}>
                    <td className="tabular font-semibold"><Link to={`/sell/invoices?sale=${sale.id}`} className="hover:underline">{sale.doc_number}</Link></td>
                    <td>{sale.sale_mode}</td>
                    <td>{sale.customer?.name ?? 'Walk-in'}</td>
                    <td className="text-right tabular">{formatKes(sale.grand_total)}</td>
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
