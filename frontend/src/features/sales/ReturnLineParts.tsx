import { ExpiryBadge } from '../inventory/ExpiryBadge'
import type { BatchRef, ItemRef, ReturnReason } from '../../lib/types'
import { RETURN_REASON_LABEL } from './returnReasons'

/**
 * The exact item on a return line: name and strength, code, and the generic
 * name, or for non-pharma items the key features in the description.
 */
export function ReturnItemCell({ product, fallbackId }: { product?: ItemRef | null; fallbackId: string }) {
  if (!product) return <span className="tabular">{fallbackId.slice(0, 8)}</span>
  const detail = [product.generic_name, product.description].filter(Boolean).join(' · ')
  return (
    <div className="min-w-0">
      <div className="font-semibold text-slate-900 leading-snug">
        {product.name}
        {product.strength && !product.name.includes(product.strength) && <span className="ml-1 text-slate-700">{product.strength}</span>}
      </div>
      <div className="text-[11px] text-slate-500 leading-snug">
        <span className="font-mono">{product.code}</span>
        {detail && <> · {detail}</>}
      </div>
    </div>
  )
}

/** Batch number with its expiry, the two facts a returned pack is traced by. */
export function ReturnBatchCell({ batch, fallbackId }: { batch?: BatchRef | null; fallbackId: string }) {
  return (
    <div className="space-y-0.5">
      <div className="tabular font-medium">{batch?.batch_number ?? fallbackId.slice(0, 8)}</div>
      {batch?.expiry_date && <ExpiryBadge date={batch.expiry_date} />}
    </div>
  )
}

export function ReturnReasonCell({ reason, remarks }: { reason: ReturnReason | null; remarks: string | null }) {
  if (!reason && !remarks) return <span className="text-slate-400">—</span>
  return (
    <div className="min-w-0">
      {reason && <div className="font-semibold text-slate-800">{RETURN_REASON_LABEL[reason] ?? reason}</div>}
      {remarks && <div className="text-[11px] text-slate-600 leading-snug">{remarks}</div>}
    </div>
  )
}
