import { AlertTriangle } from 'lucide-react'
import { Link } from 'react-router-dom'
import { formatDate } from '../../../lib/format'
import type { DashboardSummary } from '../../../lib/types'

export interface AttentionAlertsSectionProps {
  summary?: DashboardSummary
}

type Attention = {
  key: string
  label: string
  count: number
  to: string
  tone: 'red' | 'amber' | 'blue' | 'purple'
}

function resolveAttention(s?: DashboardSummary): Attention[] {
  if (!s) return []
  const items: Attention[] = []
  const inv = s.inventory
  if (inv) {
    if (inv.expired_batches_on_hand > 0)
      items.push({ key: 'expired', label: 'Expired batches still on hand', count: inv.expired_batches_on_hand, to: '/inventory/batches?status=EXPIRED', tone: 'red' })
    if (inv.quarantined_batches > 0)
      items.push({ key: 'quarantined', label: 'Batches held in quarantine', count: inv.quarantined_batches, to: '/quality/quarantine', tone: 'purple' })
    if (inv.transfers_in_transit > 0)
      items.push({ key: 'transit', label: 'Inter-branch transfers in transit', count: inv.transfers_in_transit, to: '/inventory/transfers', tone: 'blue' })
  }
  const proc = s.procurement
  if (proc) {
    if (proc.suppliers_licence_expired > 0)
      items.push({ key: 'lic-expired', label: 'Suppliers with expired PPB licence', count: proc.suppliers_licence_expired, to: '/buy/suppliers', tone: 'red' })
    if (proc.suppliers_licence_expiring_30d > 0)
      items.push({ key: 'lic-expiring', label: 'Supplier licences expiring ≤ 30 days', count: proc.suppliers_licence_expiring_30d, to: '/buy/suppliers', tone: 'amber' })
  }
  if (s.compliance && s.compliance.etims_failed > 0) {
    items.push({ key: 'etims', label: 'KRA eTIMS submissions failed', count: s.compliance.etims_failed, to: '/finance/tax-centre', tone: 'red' })
  }
  return items
}

const toneStyles: Record<Attention['tone'], { text: string; bg: string; border: string }> = {
  red: { text: 'text-rose-700', bg: 'bg-rose-500/12', border: 'border-rose-500/25' },
  amber: { text: 'text-amber-800', bg: 'bg-amber-500/15', border: 'border-amber-500/25' },
  blue: { text: 'text-sky-700', bg: 'bg-sky-500/12', border: 'border-sky-500/25' },
  purple: { text: 'text-purple-700', bg: 'bg-purple-500/12', border: 'border-purple-500/25' },
}

export function AttentionAlertsSection({ summary }: AttentionAlertsSectionProps) {
  const items = resolveAttention(summary)
  const openPeriod = summary?.finance?.open_period

  return (
    <section className="ui-card flex flex-col overflow-hidden">
      <header className="px-5 py-3.5 border-b border-[var(--border)] flex items-center justify-between">
        <div className="flex items-center gap-2">
          <div className="p-1.5 rounded-lg bg-rose-500/10 text-rose-600">
            <AlertTriangle size={16} />
          </div>
          <div>
            <h2 className="text-[13.5px] font-bold text-[var(--text)]">Action Items & Compliance</h2>
            <p className="text-[11px] text-[var(--text-muted)]">Compliance, expiry & inventory flags</p>
          </div>
        </div>
      </header>

      {items.length === 0 ? (
        <div className="p-6 text-center text-[12.5px] text-[var(--text-muted)]">
          Everything is running smoothly. Zero compliance flags today.
        </div>
      ) : (
        <ul className="divide-y divide-[var(--border)]">
          {items.map((item) => {
            const st = toneStyles[item.tone]
            return (
              <li key={item.key}>
                <Link
                  to={item.to}
                  className="flex items-center justify-between px-5 py-2.5 text-[12.5px] font-medium hover:bg-[var(--surface-2)] transition-colors group"
                >
                  <span className="text-[var(--text)] group-hover:text-rose-600 transition-colors">
                    {item.label}
                  </span>
                  <span className={`tabular font-bold px-2 py-0.5 rounded-full text-[11px] border ${st.bg} ${st.text} ${st.border}`}>
                    {item.count}
                  </span>
                </Link>
              </li>
            )
          })}
        </ul>
      )}

      {openPeriod && (
        <footer className="mt-auto px-5 py-2.5 border-t border-[var(--border)] text-[11px] text-[var(--text-muted)] tabular bg-[var(--surface-2)]">
          Active Period: FY{openPeriod.fiscal_year}/P{String(openPeriod.period_no).padStart(2, '0')} · {formatDate(openPeriod.start_date)} – {formatDate(openPeriod.end_date)}
        </footer>
      )}
    </section>
  )
}
