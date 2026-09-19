import { ShieldAlert } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { ConfirmDialog } from './ui/Modal'
import { Button } from './ui/primitives'

/**
 * Part 1.5 — consistent approve / reject with a mandatory reason on reject.
 * `canApprove` is the UI gate; the server enforces the permission again.
 */
export function ApprovalBar({
  title,
  message,
  canApprove,
  approveLabel = 'Approve',
  rejectLabel = 'Reject',
  onApprove,
  onReject,
  isPending,
  extra,
  tone = 'amber',
}: {
  title: ReactNode
  message?: ReactNode
  canApprove: boolean
  approveLabel?: string
  rejectLabel?: string
  onApprove?: () => void
  onReject?: (reason: string) => void
  isPending?: boolean
  extra?: ReactNode
  tone?: 'amber' | 'red'
}) {
  const [rejecting, setRejecting] = useState(false)
  const color = tone === 'red' ? 'var(--status-red)' : 'var(--status-amber)'
  return (
    <div
      className="flex flex-wrap items-center gap-3 px-4 py-2.5 rounded-xl border"
      style={{ borderColor: color, background: `color-mix(in srgb, ${color} 10%, transparent)` }}
    >
      <ShieldAlert size={18} style={{ color }} className="shrink-0" />
      <div className="flex-1 min-w-[200px]">
        <div className="text-sm font-bold text-slate-900">{title}</div>
        {message && <div className="text-xs text-slate-600 mt-0.5">{message}</div>}
      </div>
      {extra}
      {onReject && (
        <Button size="sm" onClick={() => setRejecting(true)} disabled={isPending || !canApprove}>
          {rejectLabel}
        </Button>
      )}
      {onApprove && (
        <Button size="sm" variant="success" onClick={onApprove} disabled={isPending || !canApprove} title={canApprove ? undefined : 'You lack the approving permission'}>
          {isPending ? 'Working…' : approveLabel}
        </Button>
      )}
      {onReject && (
        <ConfirmDialog
          open={rejecting}
          title={rejectLabel}
          requireReason="Reason for rejection"
          confirmLabel={rejectLabel}
          danger
          isPending={isPending}
          onCancel={() => setRejecting(false)}
          onConfirm={(reason) => {
            setRejecting(false)
            onReject(reason)
          }}
        />
      )}
    </div>
  )
}
