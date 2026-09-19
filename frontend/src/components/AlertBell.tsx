import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AlertTriangle, Bell, CalendarClock, PackageX } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { Link } from 'react-router-dom'
import { apiGet, apiPost } from '../lib/api'
import { formatDate } from '../lib/format'
import type { Alert, AlertSummary, Paginated } from '../lib/types'

const categoryIcon = {
  RECEIVABLE: CalendarClock,
  PAYABLE: CalendarClock,
  EXPIRY: PackageX,
} as const

const severityStyle: Record<Alert['severity'], string> = {
  CRITICAL: 'text-rose-700 bg-rose-50 border-rose-200',
  WARNING: 'text-amber-800 bg-amber-50 border-amber-200',
  INFO: 'text-sky-700 bg-sky-50 border-sky-200',
}

/**
 * Part 17 — the alert bell: money falling due and stock running out of
 * shelf life, for the active branch. The list is whatever the morning scan
 * found; acknowledging one only silences the bell, it never changes the
 * books, so a paid invoice disappears on its own at the next scan.
 */
export function AlertBell() {
  const queryClient = useQueryClient()
  const [open, setOpen] = useState(false)
  const panelRef = useRef<HTMLDivElement>(null)

  const summary = useQuery({
    queryKey: ['alerts', 'summary'],
    queryFn: () => apiGet<AlertSummary>('/api/alerts/summary'),
    refetchInterval: 5 * 60_000,
    retry: false,
  })

  const alerts = useQuery({
    queryKey: ['alerts', 'list'],
    queryFn: () => apiGet<Paginated<Alert>>('/api/alerts?per_page=20'),
    enabled: open,
    retry: false,
  })

  const acknowledge = useMutation({
    mutationFn: (id: string) => apiPost(`/api/alerts/${id}/acknowledge`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['alerts'] }),
  })

  const acknowledgeAll = useMutation({
    mutationFn: () => apiPost('/api/alerts/acknowledge-all'),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['alerts'] }),
  })

  useEffect(() => {
    if (!open) return
    const close = (e: MouseEvent) => {
      if (panelRef.current && !panelRef.current.contains(e.target as Node)) setOpen(false)
    }
    document.addEventListener('mousedown', close)
    return () => document.removeEventListener('mousedown', close)
  }, [open])

  // A user with none of the alert permissions gets a 403; show no bell at all.
  if (summary.isError) return null

  const total = summary.data?.total ?? 0
  const critical = summary.data?.critical ?? 0

  return (
    <div className="relative" ref={panelRef}>
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        title={total > 0 ? `${total} alerts need attention` : 'No open alerts'}
        aria-label="Alerts"
        className="relative p-1.5 rounded-xl text-slate-500 hover:text-slate-800 hover:bg-slate-100 border border-transparent hover:border-slate-200 transition-colors cursor-pointer"
      >
        <Bell size={17} />
        {total > 0 && (
          <span
            className={`absolute -top-0.5 -right-0.5 min-w-[16px] h-[16px] px-1 rounded-full text-[9.5px] font-bold text-white flex items-center justify-center ${critical > 0 ? 'bg-rose-600' : 'bg-amber-500'}`}
          >
            {total > 99 ? '99+' : total}
          </span>
        )}
      </button>

      {open && (
        <div className="absolute right-0 mt-2 w-[420px] max-w-[calc(100vw-2rem)] bg-white rounded-2xl border border-slate-200 shadow-xl z-50 overflow-hidden">
          <header className="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
            <div>
              <h3 className="text-[13px] font-bold text-slate-800">Alerts</h3>
              <p className="text-[11px] text-slate-500">Payment deadlines and shelf life</p>
            </div>
            {total > 0 && (
              <button
                type="button"
                onClick={() => acknowledgeAll.mutate()}
                disabled={acknowledgeAll.isPending}
                className="text-[11.5px] font-bold text-blue-600 hover:text-blue-700 hover:underline cursor-pointer"
              >
                Mark all seen
              </button>
            )}
          </header>

          <div className="max-h-[420px] overflow-y-auto">
            {alerts.isLoading && <p className="px-4 py-6 text-[12px] text-slate-500">Loading…</p>}
            {!alerts.isLoading && (alerts.data?.data.length ?? 0) === 0 && (
              <p className="px-4 py-6 text-[12px] text-slate-500">Nothing needs attention. Invoices are within terms and no stock is short-dated.</p>
            )}
            {alerts.data?.data.map((alert) => {
              const Icon = categoryIcon[alert.category] ?? AlertTriangle
              return (
                <div key={alert.id} className="px-4 py-3 border-b border-slate-50 last:border-0 hover:bg-slate-50/70">
                  <div className="flex items-start gap-2.5">
                    <span className={`mt-0.5 p-1.5 rounded-lg border ${severityStyle[alert.severity]}`}>
                      <Icon size={13} />
                    </span>
                    <div className="flex-1 min-w-0">
                      <Link to={alert.link ?? '#'} onClick={() => setOpen(false)} className="text-sm font-bold text-slate-900 hover:text-blue-600 block">
                        {alert.title}
                      </Link>
                      {alert.detail && <p className="text-xs text-slate-600 mt-0.5">{alert.detail}</p>}
                      <div className="flex items-center gap-2 mt-1">
                        {alert.due_date && <span className="text-xs text-slate-500 tabular">{formatDate(alert.due_date)}</span>}
                        <button
                          type="button"
                          onClick={() => acknowledge.mutate(alert.id)}
                          className="text-xs font-semibold text-slate-500 hover:text-slate-800 hover:underline cursor-pointer"
                        >
                          Mark seen
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
              )
            })}
          </div>
        </div>
      )}
    </div>
  )
}
