import { TrendingUp } from 'lucide-react'
import { Area, AreaChart, ResponsiveContainer, Tooltip, XAxis } from 'recharts'
import { formatKes } from '../../../lib/money'
import type { DashboardSummary, Sale, SaleMode } from '../../../lib/types'

export interface HeroSalesChartCardProps {
  salesToday?: DashboardSummary['sales_today']
  salesList: Sale[]
}

const CHANNEL_CONFIG: Record<SaleMode, { label: string; color: string; border: string; bg: string }> = {
  WHOLESALE: { label: 'Wholesale B2B', color: '#2563eb', border: 'border-t-blue-600', bg: 'bg-blue-600' },
  RETAIL: { label: 'Retail Walk-in', color: '#10b981', border: 'border-t-emerald-500', bg: 'bg-emerald-500' },
  DISPENSING: { label: 'Prescriptions', color: '#8b5cf6', border: 'border-t-purple-500', bg: 'bg-purple-500' },
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
    <section className="ui-card p-6 flex flex-col justify-between">
      <div>
        <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-4">
          <div>
            <span className="text-[12px] font-bold uppercase tracking-wider text-slate-400">
              Total Revenue Today
            </span>
            <div className="flex items-baseline gap-3 mt-1">
              <span className="text-[32px] font-black tabular tracking-tight text-slate-900">
                {formatKes(salesToday?.total ?? '0')}
              </span>
              <span className="text-[12px] font-semibold text-slate-500 tabular">
                {salesToday?.count ?? salesList.length} transaction{(salesToday?.count ?? salesList.length) === 1 ? '' : 's'} posted
              </span>
            </div>
          </div>
          {salesToday?.voided_today ? (
            <div className="text-[12px] text-rose-600 font-semibold">
              {salesToday.voided_today} sale{salesToday.voided_today === 1 ? '' : 's'} voided today
            </div>
          ) : null}
        </div>

        {hasSales ? (
          <div className="h-44 w-full my-2">
            <ResponsiveContainer width="100%" height="100%">
              <AreaChart data={chartData} margin={{ top: 10, right: 10, left: 10, bottom: 0 }}>
                <defs>
                  <linearGradient id="salesGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor="#2563eb" stopOpacity={0.25} />
                    <stop offset="95%" stopColor="#2563eb" stopOpacity={0} />
                  </linearGradient>
                </defs>
                <XAxis dataKey="time" stroke="#94a3b8" fontSize={11} tickLine={false} axisLine={false} />
                <Tooltip
                  formatter={(val: any) => [formatKes(String(val ?? 0)), 'Cumulative Revenue']}
                  contentStyle={{
                    backgroundColor: '#ffffff',
                    borderRadius: '12px',
                    border: '1px solid #e2e8f0',
                    boxShadow: '0 4px 14px rgba(0,0,0,0.06)',
                    fontSize: '12px',
                    fontWeight: 600,
                  }}
                />
                <Area type="monotone" dataKey="amount" stroke="#2563eb" strokeWidth={2.5} fillOpacity={1} fill="url(#salesGrad)" />
              </AreaChart>
            </ResponsiveContainer>
          </div>
        ) : (
          <div className="h-44 w-full my-2 flex flex-col items-center justify-center rounded-2xl bg-slate-50/60 border border-dashed border-slate-200 p-6 text-center">
            <div className="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center mb-2 shadow-2xs">
              <TrendingUp size={18} />
            </div>
            <span className="text-[13px] font-bold text-slate-700">No Sales Posted Today</span>
            <p className="text-[11.5px] text-slate-400 mt-0.5 max-w-sm">
              Real-time revenue curve will plot automatically as transactions are posted at counter desks and wholesale.
            </p>
          </div>
        )}
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-4 border-t border-slate-100">
        {channels.map((mode) => {
          const cfg = CHANNEL_CONFIG[mode]
          const data = salesToday?.by_mode[mode]
          const total = data?.total ?? '0'
          const count = data?.count ?? 0

          return (
            <div
              key={mode}
              className={`p-3.5 rounded-2xl bg-slate-50/70 border-t-3 ${cfg.border} flex flex-col justify-between`}
            >
              <div className="flex items-center justify-between text-[11.5px] font-bold text-slate-500 mb-1">
                <span>{cfg.label}</span>
                <span className="tabular">{count} sales</span>
              </div>
              <div className="text-[16px] font-extrabold text-slate-900 tabular">
                {formatKes(total)}
              </div>
            </div>
          )
        })}
      </div>
    </section>
  )
}
