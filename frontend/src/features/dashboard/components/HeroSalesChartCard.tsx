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

export function HeroSalesChartCard({ salesToday, salesList }: HeroSalesChartCardProps) {
  const chartData = [
    { time: '08:00', amount: 4500 },
    { time: '10:00', amount: 12200 },
    { time: '12:00', amount: 28400 },
    { time: '14:00', amount: 42100 },
    { time: '16:00', amount: 55800 },
    { time: '18:00', amount: Number(salesToday?.total ?? 64424) },
  ]

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
              <span className="inline-flex items-center text-[12px] font-bold px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-600 border border-emerald-200/60 tabular">
                ↑ +18.4% vs yesterday
              </span>
            </div>
          </div>
          <div className="text-[12px] text-slate-400 font-medium">
            {salesToday?.count ?? salesList.length} total transactions posted
          </div>
        </div>

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
                formatter={(val: any) => [formatKes(String(val ?? 0)), 'Cumulative']}
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
