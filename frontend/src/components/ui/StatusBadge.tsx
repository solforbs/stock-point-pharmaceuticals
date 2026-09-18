import { titleCase } from '../../lib/format'

export type StatusTone = 'green' | 'amber' | 'red' | 'blue' | 'purple' | 'cold' | 'slate' | 'teal' | 'void'

// Part 1.4 — one status vocabulary for the whole product. Colour never
// carries meaning alone: every badge also shows its text label.
const TONES: Record<string, StatusTone> = {
  POSTED: 'green', APPROVED: 'green', PAID: 'green', RELEASED: 'green', ACTIVE: 'green', CLEARED: 'green',
  MATCHED: 'green', COMPLETED: 'green', DELIVERED: 'green', FULFILLED: 'green', PICKED: 'green', OPEN: 'green',
  IN_STOCK: 'green', OK: 'green', PASSED: 'green',
  PENDING: 'amber', PENDING_APPROVAL: 'amber', PENDING_QC: 'amber', PARTIALLY_PAID: 'amber', PARTIALLY_FULFILLED: 'amber',
  IN_PROGRESS: 'amber', LOW: 'amber', NEAR_EXPIRY: 'amber', EXCEPTION: 'amber', SHORT: 'amber', CLOSING: 'amber',
  PARTIALLY_RECEIVED: 'amber', AWAITING_APPROVAL: 'amber', UNMATCHED: 'amber',
  EXPIRED: 'red', REJECTED: 'red', OVERDUE: 'red', BLOCKED: 'red', OUT_OF_STOCK: 'red', SUSPENDED: 'red',
  BLACKLISTED: 'red', CANCELLED: 'red', DISPOSED: 'red', FAILED: 'red', ON_HOLD: 'red', 'EXPIRED (BY DATE)': 'red',
  IN_TRANSIT: 'blue', RESERVED: 'blue', DISPATCHED: 'blue', SENT: 'blue', CONFIRMED: 'blue', INFO: 'blue', ACCEPTED: 'blue',
  QUARANTINED: 'purple', RECALLED: 'purple', UNDER_INVESTIGATION: 'purple', RECALL_BLOCK: 'purple',
  EXCURSION: 'cold', COLD_CHAIN: 'cold',
  DRAFT: 'slate', ARCHIVED: 'slate', DISABLED: 'slate', CLOSED: 'slate', LOCKED: 'slate', INACTIVE: 'slate',
  CONVERTED: 'slate', RETURNED_TO_SUPPLIER: 'slate', CONSUMED: 'slate',
  VOIDED: 'void', REVERSED: 'void',
  RETAIL: 'teal', WHOLESALE: 'amber', DISPENSING: 'purple',
}

const toneStyle: Record<StatusTone, { color: string; bg: string; extra?: string }> = {
  green: { color: 'var(--status-green)', bg: 'color-mix(in srgb, var(--status-green) 14%, transparent)' },
  amber: { color: '#b45309', bg: 'color-mix(in srgb, var(--status-amber) 18%, transparent)' },
  red: { color: 'var(--status-red)', bg: 'color-mix(in srgb, var(--status-red) 12%, transparent)' },
  blue: { color: 'var(--status-blue)', bg: 'color-mix(in srgb, var(--status-blue) 14%, transparent)' },
  purple: { color: 'var(--status-purple)', bg: 'color-mix(in srgb, var(--status-purple) 14%, transparent)' },
  cold: { color: 'var(--status-cold)', bg: 'color-mix(in srgb, var(--status-cold) 14%, transparent)' },
  slate: { color: 'var(--status-slate)', bg: 'color-mix(in srgb, var(--status-slate) 14%, transparent)' },
  teal: { color: 'var(--status-teal)', bg: 'color-mix(in srgb, var(--status-teal) 14%, transparent)' },
  void: { color: 'var(--status-red)', bg: 'color-mix(in srgb, var(--status-slate) 18%, transparent)', extra: 'line-through' },
}

export function toneFor(status: string | null | undefined): StatusTone {
  if (!status) return 'slate'
  return TONES[status.toUpperCase()] ?? 'slate'
}

export function StatusBadge({ status, tone, label, className = '' }: { status: string | null | undefined; tone?: StatusTone; label?: string; className?: string }) {
  const resolved = tone ?? toneFor(status)
  const style = toneStyle[resolved]
  return (
    <span
      className={`inline-flex items-center gap-1 px-1.5 py-[1px] rounded text-[10.5px] font-semibold uppercase tracking-wide whitespace-nowrap ${style.extra ?? ''} ${className}`}
      style={{ color: style.color, background: style.bg }}
    >
      <span className="w-1.5 h-1.5 rounded-full" style={{ background: style.color }} aria-hidden />
      {label ?? titleCase(status) ?? '—'}
    </span>
  )
}
