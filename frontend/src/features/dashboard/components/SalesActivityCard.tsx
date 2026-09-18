import { Bar, BarChart, ResponsiveContainer, Tooltip, XAxis } from 'recharts'
import { ShieldCheck } from 'lucide-react'

export function SalesActivityCard() {
  const data = [
    { day: 'Sun', sales: 18000, active: false },
    { day: 'Mon', sales: 34000, active: false },
    { day: 'Tue', sales: 64424, active: true },
    { day: 'Wed', sales: 41000, active: false },
    { day: 'Thu', sales: 38000, active: false },
    { day: 'Fri', sales: 52000, active: false },
    { day: 'Sat', sales: 29000, active: false },
  ]

  return (
    <section className="ui-card p-6 flex flex-col justify-between">
      <div>
        <div className="flex items-center justify-between pb-3">
          <div>
            <h3 className="text-[14px] font-bold text-slate-800 dark:text-slate-100">
              Peak Sales Day
            </h3>
            <p className="text-[11px] text-slate-400">Weekly transaction activity</p>
          </div>
          <span className="text-[18px] font-black text-blue-600 dark:text-blue-400 tabular">
            KES 64.4K
          </span>
        </div>

        <div className="h-40 w-full my-1">
          <ResponsiveContainer width="100%" height="100%">
            <BarChart data={data} margin={{ top: 10, right: 0, left: 0, bottom: 0 }}>
              <XAxis dataKey="day" stroke="#94a3b8" fontSize={11} tickLine={false} axisLine={false} />
              <Tooltip
                formatter={(val: any) => [`KES ${Number(val ?? 0).toLocaleString()}`, 'Volume']}
                contentStyle={{
                  backgroundColor: '#ffffff',
                  borderRadius: '12px',
                  border: '1px solid #e2e8f0',
                  fontSize: '11px',
                  fontWeight: 600,
                }}
              />
              <Bar
                dataKey="sales"
                radius={[8, 8, 4, 4]}
                fill="#3b82f6"
              />
            </BarChart>
          </ResponsiveContainer>
        </div>
      </div>

      <div className="p-3.5 rounded-2xl bg-emerald-50/60 dark:bg-emerald-950/30 border border-emerald-200/60 dark:border-emerald-800/50 flex items-center justify-between">
        <div className="flex items-center gap-2.5">
          <div className="p-1.5 rounded-xl bg-emerald-500 text-white">
            <ShieldCheck size={16} />
          </div>
          <div>
            <div className="text-[12px] font-bold text-emerald-800 dark:text-emerald-300">
              KRA eTIMS Transmission
            </div>
            <div className="text-[11px] text-emerald-600/90 dark:text-emerald-400">
              8 of 9 posted sales matched & signed
            </div>
          </div>
        </div>
        <span className="text-[13px] font-extrabold text-emerald-700 dark:text-emerald-300 tabular">
          89%
        </span>
      </div>
    </section>
  )
}
