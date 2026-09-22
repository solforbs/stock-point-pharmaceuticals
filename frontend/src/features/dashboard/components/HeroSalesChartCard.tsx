import { TrendingUp } from 'lucide-react'
import { Area, AreaChart, ResponsiveContainer, Tooltip, XAxis } from 'recharts'
import { formatKes } from '../../../lib/money'
import type { DashboardSummary, Sale, SaleMode } from '../../../lib/types'

export interface HeroSalesChartCardProps {
  salesToday?: DashboardSummary['sales_today']
  salesList: Sale[]
}

const CHANNEL_CONFIG: Record<SaleMode, { label: string; color: string; border: string; bg: string }> = {
  WHOLESALE: { label: 'Wholesale B2B', color: '#2563eb', border: 'border-blue-600', bg: 'bg-blue-600' },
  RETAIL: { label: 'Retail Walk-in', color: '#059669', border: 'border-emerald-600', bg: 'bg-emerald-600' },
  DISPENSING: { label: 'Prescriptions', color: '#7c3aed', border: 'border-purple-600', bg: 'bg-purple-600' },
}

function buildCumulativeSalesPoints(sales: Sale[]): { time: string; amount: number }[] {
  if (!sales.length) return []

  const valid = sales
    .filter((s) => s.posted_at || s.created_at)
    .sort((a, b) => new Date(a.posted_at ?? a.created_at!).getTime() - new Date(b.posted_at ?? b.created_at!).getTime())

  if (!valid.length) return []

  let running = 0
  const points: { time: string; amount: number }[] = []

  // Starting baseline point
  const firstTime = new Date(valid[0].posted_at ?? valid[0].created_at!)
  const firstHour = firstTime.getHours()
  const baselineHour = `${String(Math.max(0, firstHour - 1)).padStart(2, '0')}:00`
  points.push({ time: baselineHour, amount: 0 })

  for (const s of valid) {
    const d = new Date(s.posted_at ?? s.created_at!)
    const timeStr = `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`
    running += Number(s.grand_total ?? 0)
    points.push({ time: timeStr, amount: Math.round(running * 100) / 100 })
  }

  return points
}

export function HeroSalesChartCard({ salesToday, salesList }: HeroSalesChartCardProps) {
  const chartData = buildCumulativeSalesPoints(salesList)
  const totalNumber = Number(salesToday?.total ?? 0)
  const hasSales = salesList.length > 0 && totalNumber > 0 && chartData.length > 0
  const channels: SaleMode[] = ['WHOLESALE', 'RETAIL', 'DISPENSING']

  return (
    <section className="bg-white rounded-xl p-5 sm:p-6 border border-slate-200 shadow-xs flex flex-col justify-between">
      <div>
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-2">
          <div>
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
              Total Revenue Today
            </span>
            <div className="flex items-baseline gap-2.5 mt-1">
              <span className="text-2xl sm:text-3xl font-bold tabular tracking-tight text-slate-900">
                {formatKes(salesToday?.total ?? '0')}
              </span>
              <span className="text-xs text-slate-500 font-normal tabular">
                {salesToday?.count ?? salesList.length} transaction{(salesToday?.count ?? salesList.length) === 1 ? '' : 's'} posted
              </span>
            </div>
          </div>
          {salesToday?.voided_today ? (
            <div className="text-xs font-medium text-rose-700 bg-rose-50 border border-rose-200/70 px-2.5 py-1 rounded-md w-fit">
              {salesToday.voided_today} sale{salesToday.voided_today === 1 ? '' : 's'} voided today
            </div>
          ) : null}
        </div>

        {hasSales ? (
          <div className="h-52 sm:h-56 w-full my-2">
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={chartData} margin={{ top: 10, right: 10, left: 10, bottom: 0 }}>
                <defs>
                  <linearGradient id="salesGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#2563eb" stopOpacity={0.15} />
                    <stop offset="95%" stopColor="#2563eb" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <XAxis dataKey="time" stroke="#94a3b8" fontSize={11} tickLine={false} axisLine={false} />
                <Tooltip
                  formatter={(val: any) => [formatKes(String(val ?? 0)), 'Cumulative Revenue']}
                  contentStyle={{
                    backgroundColor: '#ffffff',
                    borderRadius: '8px',
                    border: '1px solid #e2e8f0',
                    boxShadow: '0 4px 12px rgba(0,0,0,0.06)',
                    fontSize: '12px',
                    fontWeight: 600,
                  }}
                />
                <Area type="monotone" dataKey="amount" stroke="#2563eb" strokeWidth={2.5} fillOpacity={1} fill="url(#salesGrad)" />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        ) : (
          <div className="h-52 sm:h-56 w-full my-2 flex flex-col items-center justify-center rounded-lg bg-slate-50 border border-slate-100 p-6 text-center">
            <div className="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center mb-2">
              <TrendingUp size={16} />
            </div>
            <span className="text-sm font-semibold text-slate-700">No Sales Posted Today</span>
            <p className="text-xs text-slate-500 mt-0.5 max-w-sm">
              Real-time revenue curve will plot automatically as transactions are posted at counter desks and wholesale.
            </p>
          </div>
        )}
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-2.5 pt-4 mt-2 border-t border-slate-100">
        {channels.map((mode) => {
          const cfg = CHANNEL_CONFIG[mode]
          const data = salesToday?.by_mode[mode]
          const total = data?.total ?? '0'
          const count = data?.count ?? 0

          return (
            <div
              key={mode}
              className="p-3 rounded-lg bg-slate-50 border border-slate-200/60 flex flex-col justify-between"
            >
              <div className="flex items-center justify-between text-xs text-slate-500 mb-1">
                <span className="flex items-center gap-1.5 font-medium">
                  <span className={`w-1.5 h-1.5 rounded-full ${cfg.bg}`} />
                  {cfg.label}
                </span>
                <span className="tabular text-slate-400">{count} sales</span>
              </div>
              <div className="text-base font-bold text-slate-900 tabular tracking-tight">
                {formatKes(total)}
              </div>
            </div>
          )
        })}
      </div>
    </section>
  )
}
