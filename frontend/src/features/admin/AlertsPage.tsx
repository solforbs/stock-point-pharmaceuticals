import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { BadgeCheck, CalendarClock, PackageX, RefreshCw } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { PageHeader } from '../../components/ui/PageHeader'
import { Button, Select } from '../../components/ui/primitives'
import { usePermissions } from '../../lib/permissions'
import { apiGet, apiPost } from '../../lib/api'
import { formatDate } from '../../lib/format'
import { formatMoney } from '../../lib/money'
import type { Alert, AlertSummary, Paginated } from '../../lib/types'

const severityStyle: Record<Alert['severity'], string> = {
  CRITICAL: 'text-rose-700 bg-rose-50 border-rose-200',
  WARNING: 'text-amber-800 bg-amber-50 border-amber-200',
  INFO: 'text-sky-700 bg-sky-50 border-sky-200',
}

const categoryLabel: Record<Alert['category'], string> = {
  RECEIVABLE: 'Customer invoices',
  PAYABLE: 'Supplier invoices',
  EXPIRY: 'Shelf life',
  LICENCE: 'Licence renewals',
  PRESCRIPTION: 'Prescriptions',
}

/**
 * Part 17 — every standing alert for the branch, which the bell only
 * summarises. Alerts are produced by `alerts:scan` each morning; this
 * screen can ask for a fresh scan but never writes an alert by hand.
 */
export default function AlertsPage() {
  const queryClient = useQueryClient()
  const perms = usePermissions()
  const [category, setCategory] = useState('')
  const [severity, setSeverity] = useState('')
  const [includeAcknowledged, setIncludeAcknowledged] = useState(true)

  const query = new URLSearchParams({ per_page: '100' })
  if (category) query.set('category', category)
  if (severity) query.set('severity', severity)
  if (includeAcknowledged) query.set('include_acknowledged', '1')

  const summary = useQuery({ queryKey: ['alerts', 'summary'], queryFn: () => apiGet<AlertSummary>('/api/alerts/summary'), retry: false })
  const alerts = useQuery({
    queryKey: ['alerts', 'page', query.toString()],
    queryFn: () => apiGet<Paginated<Alert>>(`/api/alerts?${query.toString()}`),
    retry: false,
  })

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['alerts'] })
  const rescan = useMutation({ mutationFn: () => apiPost('/api/alerts/scan'), onSuccess: invalidate })
  const acknowledge = useMutation({ mutationFn: (id: string) => apiPost(`/api/alerts/${id}/acknowledge`), onSuccess: invalidate })

  const rows = alerts.data?.data ?? []

  return (
    <div className="p-4 sm:p-6 space-y-4">
      <PageHeader
        parent="Admin"
        title="Alerts"
        subtitle="Payment deadlines and shelf-life risk, recomputed every morning at 05:30."
        actions={
          perms.has('admin.settings') ? (
            <Button variant="primary" disabled={rescan.isPending} onClick={() => rescan.mutate()}>
              <RefreshCw size={14} className={rescan.isPending ? 'animate-spin' : ''} /> {rescan.isPending ? 'Scanning…' : 'Scan now'}
            </Button>
          ) : null
        }
      />

      <div id="tour-alerts-categories" className="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3">
        {(['RECEIVABLE', 'PAYABLE', 'EXPIRY', 'LICENCE', 'PRESCRIPTION'] as const).map((key) => {
          const stats = summary.data?.by_category[key]
          const Icon = key === 'EXPIRY' ? PackageX : key === 'LICENCE' ? BadgeCheck : CalendarClock
          return (
            <div key={key} className="ui-card p-4 flex items-center gap-3">
              <span className={`p-2 rounded-xl border ${stats?.critical ? severityStyle.CRITICAL : stats?.warning ? severityStyle.WARNING : severityStyle.INFO}`}>
                <Icon size={16} />
              </span>
              <div>
                <p className="text-xs font-medium text-slate-500">{categoryLabel[key]}</p>
                <p className="text-xl font-bold text-slate-900 tabular">{stats?.total ?? 0}</p>
                {!!stats?.critical && <p className="text-xs font-semibold text-rose-600">{stats.critical} critical</p>}
              </div>
            </div>
          )
        })}
      </div>

      <div id="tour-alerts-table" className="ui-card overflow-hidden">
        <div id="tour-alerts-filters" className="px-4 py-3 border-b border-slate-100 flex flex-wrap items-center gap-2">
          <Select value={category} onChange={(e) => setCategory(e.target.value)} className="w-auto">
            <option value="">All categories</option>
            {(['RECEIVABLE', 'PAYABLE', 'EXPIRY', 'LICENCE', 'PRESCRIPTION'] as const).map((c) => (<option key={c} value={c}>{categoryLabel[c]}</option>))}
          </Select>
          <Select value={severity} onChange={(e) => setSeverity(e.target.value)} className="w-auto">
            <option value="">All severities</option>
            <option value="CRITICAL">Critical</option>
            <option value="WARNING">Warning</option>
            <option value="INFO">Info</option>
          </Select>
          <label className="flex items-center gap-2 text-xs text-slate-600 font-medium cursor-pointer">
            <input type="checkbox" checked={includeAcknowledged} onChange={(e) => setIncludeAcknowledged(e.target.checked)} />
            Include ones already seen
          </label>
        </div>

        <table className="w-full text-xs">
          <thead className="bg-slate-50 text-xs uppercase text-slate-500 tracking-wide">
            <tr>
              <th className="text-left px-4 py-2 font-bold">Alert</th>
              <th className="text-left px-4 py-2 font-bold">Category</th>
              <th className="text-left px-4 py-2 font-bold">Due</th>
              <th className="text-right px-4 py-2 font-bold">Amount</th>
              <th className="px-4 py-2" />
            </tr>
          </thead>
          <tbody>
            {rows.length === 0 && (
              <tr><td colSpan={5} className="px-4 py-8 text-center text-slate-500">
                {alerts.isLoading ? 'Loading…' : 'Nothing needs attention. Invoices are within terms and no stock is short-dated.'}
              </td></tr>
            )}
            {rows.map((alert) => (
              <tr key={alert.id} className="border-t border-slate-100 hover:bg-slate-50/70">
                <td className="px-4 py-2.5">
                  <Link to={alert.link ?? '#'} className="font-semibold text-slate-900 hover:text-blue-700">{alert.title}</Link>
                  {alert.detail && <p className="text-xs text-slate-500 mt-0.5">{alert.detail}</p>}
                </td>
                <td className="px-4 py-2.5">
                  <span className={`inline-flex px-2 py-0.5 rounded-lg border text-xs font-semibold ${severityStyle[alert.severity]}`}>
                    {categoryLabel[alert.category]}
                  </span>
                </td>
                <td className="px-4 py-2.5 tabular whitespace-nowrap">
                  {alert.due_date ? formatDate(alert.due_date) : '—'}
                  {alert.days_to_due !== null && (
                    <span className={`block text-xs font-medium ${alert.days_to_due < 0 ? 'text-rose-600' : 'text-slate-500'}`}>
                      {alert.days_to_due < 0 ? `${Math.abs(alert.days_to_due)} days late` : `in ${alert.days_to_due} days`}
                    </span>
                  )}
                </td>
                <td className="px-4 py-2.5 text-right tabular">{alert.amount ? formatMoney(alert.amount) : '—'}</td>
                <td className="px-4 py-2.5 text-right">
                  {alert.acknowledged_at ? (
                    <span className="text-xs text-slate-400 font-medium">Seen</span>
                  ) : (
                    <button type="button" onClick={() => acknowledge.mutate(alert.id)} className="text-xs font-semibold text-blue-600 hover:underline cursor-pointer">
                      Mark seen
                    </button>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
