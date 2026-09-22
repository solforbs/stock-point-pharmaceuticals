import { AlertTriangle, Building2, Calendar } from 'lucide-react'
import { Link } from 'react-router-dom'
import { formatDate } from '../../../lib/format'

export interface DashboardHeaderProps {
  userName?: string
  branchCode?: string
  branchName?: string
  dateIso: string
  periodClosed?: boolean
}

export function DashboardHeader({
  userName,
  branchCode,
  branchName,
  dateIso,
  periodClosed = false,
}: DashboardHeaderProps) {
  const hour = new Date().getHours()
  const greeting = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening'

  return (
    <header className="mb-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h1 className="text-2xl sm:text-3xl font-bold tracking-tight text-slate-900">
            {greeting}, {userName ?? 'Pharmacist'}
          </h1>
          <div className="flex items-center gap-2.5 text-xs text-slate-500 mt-1 font-normal">
            {branchCode && (
              <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md bg-slate-100 border border-slate-200 text-slate-700 font-medium">
                <Building2 size={13} className="text-slate-500" />
                {branchCode} · {branchName}
              </span>
            )}
            <span className="inline-flex items-center gap-1.5 tabular text-slate-500">
              <Calendar size={13} className="text-slate-400" />
              {formatDate(dateIso)}
            </span>
          </div>
        </div>
      </div>

      {periodClosed && (
        <div className="mt-4 p-3 rounded-lg border border-rose-200 bg-rose-50 text-xs text-rose-800 flex items-center justify-between">
          <div className="flex items-center gap-2">
            <AlertTriangle size={15} className="shrink-0 text-rose-600" />
            <span>
              <strong>No financial period is open for today.</strong> Transactions cannot post until an accounting period is activated.
            </span>
          </div>
          <Link
            to="/finance/periods"
            className="font-semibold underline hover:text-rose-900 shrink-0 ml-2"
          >
            Open Periods →
          </Link>
        </div>
      )}
    </header>
  )
}
