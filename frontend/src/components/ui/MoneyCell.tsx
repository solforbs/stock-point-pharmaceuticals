import { formatKes, formatMoney, formatQty, isNegative } from '../../lib/money'

/** Part 1.5 — KES, 2dp, tabular figures, negatives in red parentheses. */
export function MoneyCell({
  value,
  symbol = false,
  className = '',
  muted,
  title,
}: {
  value: string | number | null | undefined
  symbol?: boolean
  className?: string
  muted?: boolean
  title?: string
}) {
  const negative = isNegative(value)
  return (
    <span
      title={title}
      className={`tabular whitespace-nowrap ${negative ? 'text-rose-600' : muted ? 'text-slate-400' : ''} ${className}`}
    >
      {symbol ? formatKes(value) : formatMoney(value)}
    </span>
  )
}

export function QtyCell({ value, unit, className = '' }: { value: string | number | null | undefined; unit?: string; className?: string }) {
  const negative = isNegative(value)
  return (
    <span className={`tabular whitespace-nowrap ${negative ? 'text-rose-600' : ''} ${className}`}>
      {formatQty(value)}
      {unit ? <span className="text-slate-400 ml-1 text-xs">{unit}</span> : null}
    </span>
  )
}
