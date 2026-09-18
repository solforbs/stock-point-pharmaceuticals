import { formatKes, formatMoney, formatQty, isNegative } from '../../lib/money'

/** Part 1.5 — KES, 2dp, tabular figures, negatives in red parentheses. */
export function MoneyCell({
  value,
  symbol = false,
  className = '',
  muted,
}: {
  value: string | number | null | undefined
  symbol?: boolean
  className?: string
  muted?: boolean
}) {
  const negative = isNegative(value)
  return (
    <span
      className={`tabular whitespace-nowrap ${negative ? 'text-[var(--status-red)]' : muted ? 'text-[var(--text-muted)]' : ''} ${className}`}
    >
      {symbol ? formatKes(value) : formatMoney(value)}
    </span>
  )
}

export function QtyCell({ value, unit, className = '' }: { value: string | number | null | undefined; unit?: string; className?: string }) {
  const negative = isNegative(value)
  return (
    <span className={`tabular whitespace-nowrap ${negative ? 'text-[var(--status-red)]' : ''} ${className}`}>
      {formatQty(value)}
      {unit ? <span className="text-[var(--text-muted)] ml-1 text-[10.5px]">{unit}</span> : null}
    </span>
  )
}
