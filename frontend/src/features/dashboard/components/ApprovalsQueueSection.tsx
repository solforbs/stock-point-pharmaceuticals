import { ClipboardCheck } from 'lucide-react'
import { Link } from 'react-router-dom'
import type { ApprovalQueueKey } from '../../../lib/types'

export interface ApprovalsQueueSectionProps {
  approvals?: Partial<Record<ApprovalQueueKey, number>> | null
}

const APPROVAL_QUEUES: { key: ApprovalQueueKey; label: string; to: string }[] = [
  { key: 'requisitions', label: 'Requisitions', to: '/buy/requisitions' },
  { key: 'purchase_orders', label: 'Purchase orders', to: '/buy/purchase-orders' },
  { key: 'adjustments', label: 'Stock adjustments', to: '/inventory/adjustments' },
  { key: 'transfers', label: 'Transfers', to: '/inventory/transfers' },
  { key: 'counts', label: 'Stock counts', to: '/inventory/counts' },
  { key: 'customer_returns', label: 'Customer returns', to: '/sell/returns' },
]

export function ApprovalsQueueSection({ approvals }: ApprovalsQueueSectionProps) {
  if (!approvals) return null

  const activeQueues = APPROVAL_QUEUES.filter((q) => (approvals[q.key] ?? 0) > 0)
  const totalWaiting = activeQueues.reduce((acc, q) => acc + (approvals[q.key] ?? 0), 0)

  return (
    <section className="ui-card flex flex-col overflow-hidden">
      <header className="px-5 py-3.5 border-b border-[var(--border)] flex items-center justify-between">
        <div className="flex items-center gap-2">
          <div className="p-1.5 rounded-lg bg-[color-mix(in_srgb,var(--color-navy)_12%,transparent)] text-[var(--color-navy)] dark:text-blue-400">
            <ClipboardCheck size={16} />
          </div>
          <div>
            <h2 className="text-[13.5px] font-bold text-[var(--text)]">Approvals Waiting</h2>
            <p className="text-[11px] text-[var(--text-muted)]">Operations awaiting manager sign-off</p>
          </div>
        </div>
        <span className="tabular font-black text-[12px] px-2.5 py-0.5 rounded-full bg-amber-500/15 text-amber-800 dark:text-amber-400 border border-amber-500/20">
          {totalWaiting} Total
        </span>
      </header>

      {activeQueues.length === 0 ? (
        <div className="p-6 text-center text-[12.5px] text-[var(--text-muted)]">
          All clear! No approvals are waiting for action.
        </div>
      ) : (
        <ul className="divide-y divide-[var(--border)]">
          {activeQueues.map((q) => (
            <li key={q.key}>
              <Link
                to={q.to}
                className="flex items-center justify-between px-5 py-2.5 text-[12.5px] font-medium hover:bg-[var(--surface-2)] transition-colors group"
              >
                <span className="text-[var(--text)] group-hover:text-[var(--color-navy)] dark:group-hover:text-blue-400 transition-colors">
                  {q.label}
                </span>
                <span className="tabular font-bold px-2 py-0.5 rounded-full text-[11px] bg-amber-500/15 text-amber-800 dark:text-amber-400 border border-amber-500/25">
                  {approvals[q.key]}
                </span>
              </Link>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
