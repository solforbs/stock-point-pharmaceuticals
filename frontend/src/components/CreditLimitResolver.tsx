import { useState } from 'react'
import { Link } from 'react-router-dom'
import { getApiError } from '../lib/api'
import { formatKes } from '../lib/money'
import { usePermission } from '../lib/permissions'
import { Button, Input } from './ui/primitives'

export type CreditResolution = { payment_terms?: 'CASH_ON_DELIVERY'; credit_override_reason?: string }

/**
 * Part 10.4 — when an order would take a customer past their credit limit,
 * offer the three legitimate ways forward instead of a dead end: take it as
 * cash on delivery (no credit extended), raise the limit in Credit Control,
 * or — for a holder of customer.credit.override — override for this order
 * with a recorded reason.
 */
export function CreditLimitResolver({ error, pending, onResolve }: { error: unknown; pending: boolean; onResolve: (resolution: CreditResolution) => void }) {
  const canOverride = usePermission('customer.credit.override')
  const [reason, setReason] = useState('')
  const err = error ? getApiError(error) : null
  if (!err || err.code !== 'CREDIT_LIMIT_EXCEEDED') return null

  const details = err.details as { limit?: string; exposure?: string; shortfall?: string }
  return (
    <div className="rounded-xl border border-amber-300 bg-amber-50/60 p-3.5 space-y-2.5 text-xs text-amber-950">
      <div className="font-semibold">{err.message}</div>
      <div className="tabular text-xs text-amber-800">
        Limit {formatKes(details.limit ?? '0')} · already owed or on order {formatKes(details.exposure ?? '0')} · over by {formatKes(details.shortfall ?? '0')}
      </div>
      <div className="flex flex-wrap items-center gap-2">
        <Button size="sm" variant="primary" disabled={pending} onClick={() => onResolve({ payment_terms: 'CASH_ON_DELIVERY' })}>
          Continue as cash on delivery
        </Button>
        <Link to="/customers/credit-control" className="text-xs text-blue-700 hover:text-blue-800 hover:underline font-semibold">Set a credit limit</Link>
      </div>
      {canOverride && (
        <div className="flex flex-wrap items-center gap-2 pt-1 border-t border-[var(--border)]">
          <Input className="flex-1 min-w-[220px]" placeholder="Reason for overriding the limit (recorded)" value={reason} onChange={(e) => setReason(e.target.value)} />
          <Button size="sm" variant="danger" disabled={pending || reason.trim().length < 5} onClick={() => onResolve({ credit_override_reason: reason.trim() })}>
            Override limit
          </Button>
        </div>
      )}
    </div>
  )
}
