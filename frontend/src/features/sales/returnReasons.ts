import type { ReturnReason } from '../../lib/types'

/** Mirrors App\Services\Inventory\ReturnReasons — why a line came back. */
export const RETURN_REASON_LABEL: Record<ReturnReason, string> = {
  DAMAGED: 'Damaged',
  EXPIRED: 'Expired',
  SHORT_EXPIRY: 'Short expiry',
  WRONG_ITEM: 'Wrong item supplied',
  EXCESS_QUANTITY: 'Excess quantity',
  QUALITY_COMPLAINT: 'Quality complaint',
  ADVERSE_REACTION: 'Adverse reaction',
  RECALL: 'Recall',
  NOT_REQUIRED: 'No longer required',
  NOT_ORDERED: 'Not ordered',
  SLOW_MOVING: 'Slow moving',
  OTHER: 'Other (explain in remarks)',
}

export const CUSTOMER_RETURN_REASONS: ReturnReason[] = [
  'DAMAGED', 'EXPIRED', 'SHORT_EXPIRY', 'WRONG_ITEM', 'EXCESS_QUANTITY',
  'QUALITY_COMPLAINT', 'ADVERSE_REACTION', 'RECALL', 'NOT_REQUIRED', 'OTHER',
]

export const SUPPLIER_RETURN_REASONS: ReturnReason[] = [
  'DAMAGED', 'EXPIRED', 'SHORT_EXPIRY', 'WRONG_ITEM', 'NOT_ORDERED', 'EXCESS_QUANTITY',
  'QUALITY_COMPLAINT', 'RECALL', 'SLOW_MOVING', 'OTHER',
]

/** OTHER says nothing on its own, so it needs remarks (the server enforces the same). */
export function returnLineExplained(reason: ReturnReason | '' | null, remarks: string): boolean {
  return reason !== 'OTHER' || remarks.trim().length > 0
}
