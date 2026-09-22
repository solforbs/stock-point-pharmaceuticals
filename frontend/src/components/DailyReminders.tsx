import { useQuery } from '@tanstack/react-query'
import { BadgeCheck, CalendarClock, PackageX, Sparkles, TrendingUp } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useCurrentUser } from '../hooks/useCurrentUser'
import { apiGet } from '../lib/api'
import { formatMoney } from '../lib/money'
import type { Alert } from '../lib/types'
import { Modal } from './ui/Modal'
import { Button } from './ui/primitives'

type BriefingCategory = {
  category: Alert['category']
  total: number
  critical: number
  amount: string | null
  items: Pick<Alert, 'id' | 'severity' | 'title' | 'detail' | 'link' | 'due_date'>[]
}

type Briefing = {
  name: string
  date: string
  sales: { today: { count: number; total: string }; yesterday: { count: number; total: string } } | null
  categories: BriefingCategory[]
}

const CATEGORY: Record<Alert['category'], { label: string; icon: typeof CalendarClock; link: string }> = {
  RECEIVABLE: { label: 'Customer debts', icon: CalendarClock, link: '/customers/credit-control' },
  PAYABLE: { label: 'Supplier payments', icon: CalendarClock, link: '/finance/payables' },
  EXPIRY: { label: 'Expiring stock', icon: PackageX, link: '/inventory/batches' },
  LICENCE: { label: 'Licence renewals', icon: BadgeCheck, link: '/quality/licences' },
}

const SEEN_KEY = 'daily-reminders-seen'

function greeting(): string {
  const hour = new Date().getHours()
  if (hour < 12) return 'Good morning'
  if (hour < 17) return 'Good afternoon'
  return 'Good evening'
}

function seenToday(userId: number | undefined, today: string): boolean {
  try {
    return localStorage.getItem(`${SEEN_KEY}:${userId}`) === today
  } catch {
    return false
  }
}

function markSeen(userId: number | undefined, today: string) {
  try {
    localStorage.setItem(`${SEEN_KEY}:${userId}`, today)
  } catch {
    // storage disabled: the reminder simply shows again next time
  }
}

/**
 * The assistant's reminder, shown once a day when someone first opens the
 * system: sales so far, and the debts, supplier payments, expiring stock and
 * licence renewals that need attention. The sparkle button beside the bell
 * brings it back at any time.
 */
export function DailyReminders() {
  const { data: user } = useCurrentUser()
  const today = new Date().toISOString().slice(0, 10)
  const [open, setOpen] = useState(false)

  const briefing = useQuery({
    queryKey: ['reminders', 'today', user?.active_branch?.id],
    queryFn: () => apiGet<Briefing>('/api/reminders/today'),
    enabled: !!user?.active_branch,
    staleTime: 5 * 60_000,
    meta: { silent: true },
  })

  const hasSomething = !!briefing.data && (briefing.data.categories.length > 0 || !!briefing.data.sales)

  useEffect(() => {
    if (hasSomething && !seenToday(user?.id, today)) {
      setOpen(true)
    }
  }, [hasSomething, user?.id, today])

  function close() {
    markSeen(user?.id, today)
    setOpen(false)
  }

  if (!user?.active_branch) return null
  const data = briefing.data
  const firstName = (data?.name ?? user.name ?? '').split(' ')[0]

  return (
    <>
      <button
        type="button"
        onClick={() => setOpen(true)}
        title="Today's reminders"
        aria-label="Today's reminders"
        className="p-2 rounded-lg text-slate-500 hover:text-blue-700 hover:bg-blue-50 transition-colors cursor-pointer"
      >
        <Sparkles size={18} />
      </button>

      <Modal
        open={open && !!data}
        onClose={close}
        width={560}
        title={<span className="flex items-center gap-2"><Sparkles size={18} className="text-blue-600" /> {greeting()}, {firstName}</span>}
        footer={
          <div className="flex justify-between items-center w-full gap-2">
            <Link to="/admin/alerts" onClick={close} className="text-sm font-semibold text-blue-600 hover:underline">See all alerts</Link>
            <Button variant="primary" onClick={close}>Got it</Button>
          </div>
        }
      >
        {data && (
          <div className="space-y-4 text-sm">
            <p className="text-slate-600">
              {data.categories.length === 0
                ? 'Nothing needs your attention right now.'
                : `Here is what needs attention today (${data.categories.reduce((n, c) => n + c.total, 0)} item${data.categories.reduce((n, c) => n + c.total, 0) === 1 ? '' : 's'}).`}
            </p>

            {data.sales && (
              <div className="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50/60 px-3.5 py-2.5">
                <TrendingUp size={18} className="text-emerald-600 shrink-0" />
                <div className="text-emerald-900">
                  <strong>Sales today:</strong> {formatMoney(data.sales.today.total)} from {data.sales.today.count} sale{data.sales.today.count === 1 ? '' : 's'}
                  <span className="text-emerald-700"> · yesterday {formatMoney(data.sales.yesterday.total)} ({data.sales.yesterday.count})</span>
                </div>
              </div>
            )}

            {data.categories.map((c) => {
              const meta = CATEGORY[c.category]
              const Icon = meta.icon
              return (
                <section key={c.category} className="space-y-1.5">
                  <header className="flex items-center justify-between">
                    <h3 className="flex items-center gap-1.5 font-semibold text-slate-800">
                      <Icon size={15} className={c.critical > 0 ? 'text-rose-600' : 'text-amber-600'} /> {meta.label}
                      <span className="text-xs font-medium text-slate-500">
                        {c.total}{c.critical > 0 ? ` · ${c.critical} urgent` : ''}{c.amount ? ` · ${formatMoney(c.amount)}` : ''}
                      </span>
                    </h3>
                    <Link to={meta.link} onClick={close} className="text-xs font-semibold text-blue-600 hover:underline">Open</Link>
                  </header>
                  <ul className="space-y-1">
                    {c.items.map((item) => (
                      <li key={item.id} className={`rounded-lg border px-3 py-1.5 ${item.severity === 'CRITICAL' ? 'border-rose-200 bg-rose-50/60' : item.severity === 'WARNING' ? 'border-amber-200 bg-amber-50/50' : 'border-slate-200'}`}>
                        {item.link ? <Link to={item.link} onClick={close} className="font-medium text-slate-800 hover:underline">{item.title}</Link> : <span className="font-medium text-slate-800">{item.title}</span>}
                        {item.detail && <div className="text-xs text-slate-500">{item.detail}</div>}
                      </li>
                    ))}
                  </ul>
                  {c.total > c.items.length && <p className="text-xs text-slate-500">and {c.total - c.items.length} more.</p>}
                </section>
              )
            })}
          </div>
        )}
      </Modal>
    </>
  )
}
