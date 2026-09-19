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
  const q = s.quality
  if (q) {
    if (q.cold_chain_open_excursions)
      items.push({ key: 'coldchain', label: 'Cold-chain excursions to review', count: q.cold_chain_open_excursions, to: '/quality/cold-chain', tone: 'red' })
    if (q.licences_expired)
      items.push({ key: 'own-lic-expired', label: 'Own licences expired', count: q.licences_expired, to: '/quality/licences', tone: 'red' })
    if (q.licences_expiring_60d)
      items.push({ key: 'own-lic-expiring', label: 'Own licences expiring ≤ 60 days', count: q.licences_expiring_60d, to: '/quality/licences', tone: 'amber' })
    if (q.adr_draft_reports)
      items.push({ key: 'adr', label: 'Adverse-reaction reports in draft', count: q.adr_draft_reports, to: '/quality/pharmacovigilance', tone: 'amber' })
    if (q.documents_to_acknowledge)
      items.push({ key: 'sops', label: 'SOPs you have not read yet', count: q.documents_to_acknowledge, to: '/quality/sops', tone: 'blue' })
  }
  if (s.people?.leave_pending)
    items.push({ key: 'leave', label: 'Leave requests awaiting approval', count: s.people.leave_pending, to: '/people/leave', tone: 'amber' })
  return items
}

const toneStyles: Record<Attention['tone'], { text: string; bg: string; border: string }> = {
  red: { text: 'text-rose-800', bg: 'bg-rose-50', border: 'border-rose-200/70' },
  amber: { text: 'text-amber-800', bg: 'bg-amber-50', border: 'border-amber-200/70' },
  blue: { text: 'text-blue-800', bg: 'bg-blue-50', border: 'border-blue-200/70' },
  purple: { text: 'text-purple-800', bg: 'bg-purple-50', border: 'border-purple-200/70' },
}

export function AttentionAlertsSection({ summary }: AttentionAlertsSectionProps) {
  const items = resolveAttention(summary)
  const openPeriod = summary?.finance?.open_period

  return (
    <section className="bg-white rounded-xl p-5 sm:p-6 border border-slate-200 shadow-xs flex flex-col justify-between">
      <div>
        <header className="flex items-center justify-between pb-3 border-b border-slate-100">
          <div className="flex items-center gap-2.5">
            <div className="w-8 h-8 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center">
              <AlertTriangle size={16} />
            </div>
            <div>
              <h2 className="text-sm font-semibold text-slate-900">Action Items & Compliance</h2>
              <p className="text-xs text-slate-500 font-normal">Compliance, expiry & inventory flags</p>
            </div>
          </div>
          {items.length > 0 && (
            <span className="tabular font-semibold text-xs px-2 py-0.5 rounded-md bg-rose-50 border border-rose-200/70 text-rose-800">
              {items.length} Alerts
            </span>
          )}
        </header>

        {items.length === 0 ? (
          <div className="p-6 text-center text-xs text-slate-400">
            Everything is running smoothly. Zero compliance flags today.
          </div>
        ) : (
          <div className="space-y-0.5 pt-2">
            {items.map((item) => {
              const st = toneStyles[item.tone]
              return (
                <Link
                  key={item.key}
                  to={item.to}
                  className="flex items-center justify-between px-3 py-2 rounded-lg hover:bg-slate-50 transition-colors group"
                >
                  <span className="text-sm text-slate-700 group-hover:text-slate-900 transition-colors">
                    {item.label}
                  </span>
                  <span className={`tabular font-semibold px-2 py-0.5 rounded-md text-xs border ${st.bg} ${st.text} ${st.border}`}>
                    {item.count}
                  </span>
                </Link>
              )
            })}
          </div>
        )}
      </div>

      {openPeriod && (
        <div className="pt-3 mt-3 border-t border-slate-100 text-xs text-slate-500 tabular flex items-center justify-between">
          <span>Active Fiscal Period</span>
          <span className="font-medium text-slate-700">
            FY{openPeriod.fiscal_year}/P{String(openPeriod.period_no).padStart(2, '0')} · {formatDate(openPeriod.start_date)} – {formatDate(openPeriod.end_date)}
          </span>
        </div>
      )}
    </section>
  )
}
