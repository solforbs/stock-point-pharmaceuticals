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
    <section id="tour-approvals-queue" className="bg-white rounded-[26px] p-6 shadow-[0_2px_12px_-2px_rgba(15,23,42,0.03),0_10px_28px_-6px_rgba(15,23,42,0.03)] border-0 flex flex-col">
      <header className="flex items-center justify-between pb-4">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center">
            <ClipboardCheck size={18} />
          </div>
          <div>
            <h2 className="text-[14.5px] font-bold text-slate-900">Approvals Waiting</h2>
            <p className="text-[11.5px] text-slate-400 font-medium">Operations awaiting manager sign-off</p>
          </div>
        </div>
        <span className="tabular font-bold text-[11px] px-3 py-1 rounded-full bg-amber-50 text-amber-800">
          {totalWaiting} Total
        </span>
      </header>

      {activeQueues.length === 0 ? (
        <div className="p-6 text-center text-[12.5px] text-slate-400">
          All clear! No approvals are waiting for action.
        </div>
      ) : (
        <div className="space-y-1.5 pt-1">
          {activeQueues.map((q) => (
            <Link
              key={q.key}
              to={q.to}
              className="flex items-center justify-between px-3.5 py-2.5 rounded-xl hover:bg-slate-50 transition-colors group"
            >
              <span className="text-[13px] font-medium text-slate-700 group-hover:text-blue-600 transition-colors">
                {q.label}
              </span>
              <span className="tabular font-bold px-2.5 py-0.5 rounded-full text-[11px] bg-amber-50 text-amber-700">
                {approvals[q.key]}
              </span>
            </Link>
          ))}
        </div>
      )}
    </section>
  )
}
