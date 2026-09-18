import { StatusBadge } from '../../components/ui/StatusBadge'
import { expiryTier, formatDate } from '../../lib/format'

export interface ExpiryBadgeProps {
  date: string | null | undefined
}

export function ExpiryBadge({ date }: ExpiryBadgeProps) {
  if (!date) return <span className="text-[var(--text-muted)]">—</span>

  const tier = expiryTier(date)
  const tone =
    tier === 'expired' ? 'red' :
    tier === 'd30' ? 'red' :
    tier === 'd90' ? 'amber' :
    tier === 'd180' ? 'amber' : 'green'

  const label =
    tier === 'expired' ? 'Expired' :
    tier === 'd30' ? '≤ 30 d' :
    tier === 'd90' ? '≤ 90 d' :
    tier === 'd180' ? '≤ 180 d' : 'OK'

  return (
    <span className="inline-flex items-center gap-1.5 tabular whitespace-nowrap">
      <span className="font-medium">{formatDate(date)}</span>
      <StatusBadge status={label} tone={tone} label={label} />
    </span>
  )
}
