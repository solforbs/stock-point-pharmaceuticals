import { MoneyCell, QtyCell } from '../../components/ui/MoneyCell'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { formatDate, formatDateTime } from '../../lib/format'
import { formatPct } from '../../lib/money'
import type { ReportColumn } from '../../lib/types'

/** Part 20 — one cell renderer for every typed report column, shared by the catalogue viewer and the dedicated report screens. */
export function formatCell(value: unknown, column: ReportColumn) {
  if (value === null || value === undefined || value === '') return <span className="text-slate-400">—</span>
  switch (column.type) {
    case 'money':
      return <MoneyCell value={String(value)} />
    case 'qty':
      return <QtyCell value={String(value)} />
    case 'pct':
      return <span className="tabular">{formatPct(String(value))}</span>
    case 'int':
      return <span className="tabular">{Number(value).toLocaleString('en-KE')}</span>
    case 'date':
      return <span className="tabular">{formatDate(String(value))}</span>
    case 'datetime':
      return <span className="tabular">{formatDateTime(String(value))}</span>
    case 'bool':
      return <StatusBadge status={value ? 'OK' : 'FAILED'} label={value ? 'Yes' : 'No'} />
    default:
      if (typeof value === 'object') return <span className="text-xs font-mono text-slate-600">{JSON.stringify(value)}</span>
      if (typeof value === 'string' && /^[A-Z_]{3,}$/.test(value)) return <StatusBadge status={value} />
      return String(value)
  }
}

export function isNumericType(type?: string) {
  return type === 'money' || type === 'qty' || type === 'pct' || type === 'int'
}
