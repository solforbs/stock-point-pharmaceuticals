const dateFmt = new Intl.DateTimeFormat('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
const dateTimeFmt = new Intl.DateTimeFormat('en-GB', {
  day: '2-digit',
  month: 'short',
  year: 'numeric',
  hour: '2-digit',
  minute: '2-digit',
})

export function formatDate(value: string | null | undefined): string {
  if (!value) return '—'
  const d = new Date(value.length === 10 ? `${value}T00:00:00` : value)
  return Number.isNaN(d.getTime()) ? value : dateFmt.format(d)
}

export function formatDateTime(value: string | null | undefined): string {
  if (!value) return '—'
  const d = new Date(value)
  return Number.isNaN(d.getTime()) ? value : dateTimeFmt.format(d)
}

function isoDate(d: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`
}

export function todayIso(): string {
  return isoDate(new Date())
}

export function addDaysIso(days: number): string {
  const d = new Date()
  d.setDate(d.getDate() + days)
  return isoDate(d)
}

export function daysUntil(date: string | null | undefined): number | null {
  if (!date) return null
  const target = new Date(`${date.slice(0, 10)}T00:00:00`)
  if (Number.isNaN(target.getTime())) return null
  const now = new Date()
  now.setHours(0, 0, 0, 0)
  return Math.round((target.getTime() - now.getTime()) / 86_400_000)
}

export type ExpiryTier = 'expired' | 'd30' | 'd90' | 'd180' | 'ok'

/** Blueprint expiry tiers: 30 / 90 / 180 days, colour-coded everywhere. */
export function expiryTier(date: string | null | undefined): ExpiryTier {
  const days = daysUntil(date)
  if (days === null) return 'ok'
  if (days < 0) return 'expired'
  if (days <= 30) return 'd30'
  if (days <= 90) return 'd90'
  if (days <= 180) return 'd180'
  return 'ok'
}

export function titleCase(value: string | null | undefined): string {
  if (!value) return ''
  return value.toLowerCase().replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase())
}

export function shortId(id: string | null | undefined): string {
  return id ? id.slice(0, 8) : '—'
}
