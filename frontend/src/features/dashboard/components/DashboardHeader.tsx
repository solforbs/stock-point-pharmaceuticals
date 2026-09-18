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
          <h1 className="text-[22px] font-black tracking-tight text-[var(--text)]">
            {greeting}, {userName ?? 'Pharmacist'}
          </h1>
          <div className="flex items-center gap-3 text-[12px] text-[var(--text-muted)] mt-1 font-medium">
            {branchCode && (
              <span className="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-[var(--surface-2)] text-[var(--text-secondary)] border border-[var(--border)] font-semibold text-[11px]">
                <Building2 size={12} className="text-[var(--color-navy)]" />
                {branchCode} · {branchName}
              </span>
            )}
            <span className="inline-flex items-center gap-1.5 tabular text-[11.5px]">
              <Calendar size={12} />
              {formatDate(dateIso)}
            </span>
          </div>
        </div>
      </div>

      {periodClosed && (
        <div className="mt-4 p-3 rounded-xl border border-rose-500/30 bg-rose-500/10 flex items-center justify-between text-[12px] text-rose-700">
          <div className="flex items-center gap-2">
            <AlertTriangle size={16} className="shrink-0" />
            <span>
              <strong>No financial period is open for today.</strong> Nothing can post until a period is opened.
            </span>
          </div>
          <Link
            to="/finance/periods"
            className="font-bold underline hover:opacity-80 shrink-0 ml-2"
          >
            Open Periods →
          </Link>
        </div>
      )}
    </header>
  )
}
