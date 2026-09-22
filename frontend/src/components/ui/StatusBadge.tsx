import { titleCase } from '../../lib/format'

export type StatusTone = 'green' | 'amber' | 'red' | 'blue' | 'purple' | 'cold' | 'slate' | 'teal' | 'void'

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
  RETAIL: 'blue', WHOLESALE: 'amber', DISPENSING: 'purple',
}

const toneStyles: Record<StatusTone, { text: string; bg: string; dot: string; extra?: string }> = {
  green: { text: 'text-emerald-800', bg: 'bg-emerald-50/80 border-emerald-200/70', dot: 'bg-emerald-500' },
  amber: { text: 'text-amber-800', bg: 'bg-amber-50/80 border-amber-200/70', dot: 'bg-amber-500' },
  red: { text: 'text-rose-800', bg: 'bg-rose-50/80 border-rose-200/70', dot: 'bg-rose-500' },
  blue: { text: 'text-blue-800', bg: 'bg-blue-50/80 border-blue-200/70', dot: 'bg-blue-500' },
  purple: { text: 'text-purple-800', bg: 'bg-purple-50/80 border-purple-200/70', dot: 'bg-purple-500' },
  cold: { text: 'text-sky-800', bg: 'bg-sky-50/80 border-sky-200/70', dot: 'bg-sky-500' },
  slate: { text: 'text-slate-700', bg: 'bg-slate-100 border-slate-200/80', dot: 'bg-slate-400' },
  teal: { text: 'text-teal-800', bg: 'bg-teal-50/80 border-teal-200/70', dot: 'bg-teal-500' },
  void: { text: 'text-slate-500', bg: 'bg-slate-100 border-slate-200/80', dot: 'bg-slate-400', extra: 'line-through opacity-75' },
}

export function toneFor(status: string | null | undefined): StatusTone {
  if (!status) return 'slate'
  return TONES[status.toUpperCase()] ?? 'slate'
}

export function StatusBadge({
  status,
  tone,
  label,
  className = '',
}: {
  status: string | null | undefined
  tone?: StatusTone
  label?: string
  className?: string
}) {
  const resolved = tone ?? toneFor(status)
  const st = toneStyles[resolved]

  return (
    <span
      className={`inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium tracking-normal border ${st.bg} ${st.text} ${st.extra ?? ''} ${className}`}
    >
      <span className={`w-1.5 h-1.5 rounded-full ${st.dot}`} aria-hidden />
      {label ?? titleCase(status) ?? '—'}
    </span>
  )
}
