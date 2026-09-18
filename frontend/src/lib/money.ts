import { toUnits } from './decimal'

const kes = new Intl.NumberFormat('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const qty = new Intl.NumberFormat('en-KE', { minimumFractionDigits: 0, maximumFractionDigits: 4 })
const pct = new Intl.NumberFormat('en-KE', { minimumFractionDigits: 0, maximumFractionDigits: 2 })

/** KES, 2dp, thousands separators, negatives in parentheses. Display only. */
export function formatKes(value: string | number | null | undefined, options: { symbol?: boolean } = {}): string {
  if (value === null || value === undefined || value === '') return '—'
  const n = Number(value)
  if (Number.isNaN(n)) return String(value)
  const body = kes.format(Math.abs(n))
  const symbol = options.symbol === false ? '' : 'KES '
  return n < 0 ? `(${symbol}${body})` : `${symbol}${body}`
}

export function formatMoney(value: string | number | null | undefined): string {
  return formatKes(value, { symbol: false })
}

/** Quantities: up to 4dp, trailing zeros trimmed. */
export function formatQty(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') return '—'
  const n = Number(value)
  if (Number.isNaN(n)) return String(value)
  return qty.format(n)
}

export function formatPct(value: string | number | null | undefined): string {
  if (value === null || value === undefined || value === '') return '—'
  const n = Number(value)
  if (Number.isNaN(n)) return String(value)
  return `${pct.format(n)}%`
}

export function isNegative(value: string | number | null | undefined): boolean {
  if (value === null || value === undefined) return false
  return toUnits(value) < 0n
}
