/** Why a supplier may be chosen without a bid analysis (mirrors PurchaseOrder::SINGLE_SOURCE_REASONS). */
export const SINGLE_SOURCE_REASONS: { value: string; label: string }[] = [
  { value: 'DIRECT_DELIVERY', label: 'Supplier delivers direct to us' },
  { value: 'SOLE_SUPPLIER', label: 'Only supplier holding the item at the moment' },
  { value: 'SOLE_DISTRIBUTOR', label: 'Manufacturer or sole licensed distributor' },
  { value: 'URGENT_NEED', label: 'Urgent or emergency need' },
  { value: 'AGREED_PRICE', label: 'Existing agreement or contract price' },
  { value: 'CONTINUITY', label: 'Same brand or batch continuity for patients' },
  { value: 'OTHER', label: 'Other (explained in the justification)' },
]

export function singleSourceReasonLabel(value: string | null | undefined): string {
  return SINGLE_SOURCE_REASONS.find((r) => r.value === value)?.label ?? value ?? '—'
}
