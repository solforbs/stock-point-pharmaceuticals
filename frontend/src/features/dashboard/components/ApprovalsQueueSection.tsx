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
    <section id="tour-approvals-queue" className="bg-white rounded-xl p-5 sm:p-6 border border-slate-200 shadow-xs flex flex-col">
      <header className="flex items-center justify-between pb-3 border-b border-slate-100">
        <div className="flex items-center gap-2.5">
          <div className="w-8 h-8 rounded-lg bg-slate-100 text-slate-700 flex items-center justify-center">
            <ClipboardCheck size={16} />
          </div>
          <div>
            <h2 className="text-sm font-semibold text-slate-900">Approvals Waiting</h2>
            <p className="text-xs text-slate-500 font-normal">Operations awaiting manager sign-off</p>
          </div>
        </div>
        <span className="tabular font-semibold text-xs px-2 py-0.5 rounded-md bg-amber-50 border border-amber-200/70 text-amber-800">
          {totalWaiting} Total
        </span>
      </header>

      {activeQueues.length === 0 ? (
        <div className="p-6 text-center text-xs text-slate-400">
          All clear! No approvals are waiting for action.
        </div>
      ) : (
        <div className="space-y-0.5 pt-2">
          {activeQueues.map((q) => (
            <Link
              key={q.key}
              to={q.to}
              className="flex items-center justify-between px-3 py-2 rounded-lg hover:bg-slate-50 transition-colors group"
            >
              <span className="text-sm text-slate-700 group-hover:text-blue-600 transition-colors">
                {q.label}
              </span>
              <span className="tabular font-semibold px-2 py-0.5 rounded-md text-xs bg-amber-50 border border-amber-200/70 text-amber-800">
                {approvals[q.key]}
              </span>
            </Link>
          ))}
        </div>
      )}
    </section>
  )
}
