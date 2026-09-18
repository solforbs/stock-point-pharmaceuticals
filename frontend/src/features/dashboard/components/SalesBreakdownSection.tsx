import { TrendingUp } from 'lucide-react'
import { formatKes } from '../../../lib/money'
import type { DashboardSummary, SaleMode } from '../../../lib/types'

export interface SalesBreakdownSectionProps {
  salesToday?: DashboardSummary['sales_today']
}

const MODE_CONFIG: Record<SaleMode, { label: string; color: string; bg: string }> = {
  WHOLESALE: { label: 'Wholesale Orders', color: '#b45309', bg: 'bg-amber-500' },
  RETAIL: { label: 'Retail Walk-in (POS)', color: 'var(--color-navy)', bg: 'bg-blue-600' },
  DISPENSING: { label: 'Prescription Dispensing', color: '#6d28d9', bg: 'bg-purple-600' },
}

export function SalesBreakdownSection({ salesToday }: SalesBreakdownSectionProps) {
  if (!salesToday) return null

  const modes: SaleMode[] = ['WHOLESALE', 'RETAIL', 'DISPENSING']
  const totalAmount = Number(salesToday.total ?? 0)

  return (
    <section className="ui-card p-5">
      <header className="flex items-center justify-between pb-4 border-b border-[var(--border)] mb-4">
        <div className="flex items-center gap-2">
          <div className="p-1.5 rounded-lg bg-emerald-500/10 text-emerald-600">
            <TrendingUp size={16} />
          </div>
          <div>
            <h2 className="text-[14px] font-bold text-[var(--text)]">Sales by Channel Today</h2>
            <p className="text-[11px] text-[var(--text-muted)]">Real-time revenue split across active sales desks</p>
          </div>
        </div>
        <span className="text-[14px] font-black tabular text-[var(--text)]">
          {formatKes(salesToday.total)}
        </span>
      </header>

      <div className="space-y-4">
        {modes.map((mode) => {
          const data = salesToday.by_mode[mode]
          const count = data?.count ?? 0
          const amount = Number(data?.total ?? 0)
          const pct = totalAmount > 0 ? Math.round((amount / totalAmount) * 100) : 0
          const config = MODE_CONFIG[mode]

          return (
            <div key={mode} className="group">
              <div className="flex items-center justify-between text-[12.5px] mb-1.5">
                <span className="font-semibold text-[var(--text)] flex items-center gap-2">
                  <span className={`w-2 h-2 rounded-full ${config.bg}`} />
                  {config.label}
                  <span className="text-[11px] text-[var(--text-muted)] font-normal">
                    ({count} transaction{count === 1 ? '' : 's'})
                  </span>
                </span>
                <div className="text-right tabular font-bold">
                  <span>{formatKes(String(amount))}</span>
                  <span className="text-[11px] text-[var(--text-muted)] ml-2">({pct}%)</span>
                </div>
              </div>
              <div className="w-full h-2 rounded-full bg-[var(--surface-3)] overflow-hidden">
                <div
                  className={`h-full rounded-full transition-all duration-500 ${config.bg}`}
                  style={{ width: `${pct}%` }}
                />
              </div>
            </div>
          )
        })}
      </div>
    </section>
  )
}
