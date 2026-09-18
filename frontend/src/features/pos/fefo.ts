import { dCmp, dIsPos, dMax, dSub } from '../../lib/decimal'
import type { StockStateRow } from '../../lib/types'

export type FefoAllocation = { batch_id: string; batch_number: string; expiry_date: string; qty_base: string }

/**
 * Part 24.2 "Batch info — FEFO preview per line". The quote endpoint does
 * not return allocations, so this previews them from the store's released
 * batches in expiry order. Display only: the server allocates at checkout.
 */
export function previewFefo(row: StockStateRow | undefined, qtyBase: string): { allocations: FefoAllocation[]; shortfall: string } {
  let remaining = qtyBase
  const allocations: FefoAllocation[] = []
  if (row) {
    const batches = row.batches
      .filter((b) => b.status === 'RELEASED')
      .sort((a, b) => a.expiry_date.localeCompare(b.expiry_date))
    for (const batch of batches) {
      if (!dIsPos(remaining)) break
      const free = dMax(dSub(batch.on_hand, batch.reserved), '0')
      if (!dIsPos(free)) continue
      const take = dCmp(free, remaining) >= 0 ? remaining : free
      allocations.push({ batch_id: batch.batch_id, batch_number: batch.batch_number, expiry_date: batch.expiry_date, qty_base: take })
      remaining = dSub(remaining, take)
    }
  }
  return { allocations, shortfall: dIsPos(remaining) ? remaining : '0.0000' }
}
