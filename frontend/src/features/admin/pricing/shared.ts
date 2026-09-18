import type { Decimal, Product } from '../../../lib/types'

export type Ref = { id: string; code: string; name: string }

export type PromotionType = 'PRICE_OVERRIDE' | 'PERCENT_OFF' | 'BUY_X_GET_Y'

export type PromotionLine = {
  id?: string
  product_id: string
  uom_id: string
  buy_qty: Decimal | null
  free_qty: Decimal | null
  bonus_product_id: string | null
  promo_price: Decimal | null
  discount_pct: Decimal | null
  max_free_per_order: Decimal | null
  repeat: boolean
  product?: Ref | null
  bonus_product?: Ref | null
}

export type Promotion = {
  id: string
  code: string
  name: string
  promo_type: PromotionType
  effective_from: string
  effective_to: string
  customer_scope: string | null
  branch_scope: string | null
  funded_by: 'SUPPLIER' | 'US'
  supplier_id: string | null
  is_active: boolean
  lines_count?: number
  lines?: PromotionLine[]
  supplier?: Ref | null
  customer?: Ref | null
  branch?: Ref | null
}

export type PriceBreak = {
  id: string
  product_price_id: string
  min_qty: Decimal
  max_qty: Decimal | null
  unit_price: Decimal
  break_type: 'STEP' | 'MARGINAL'
  product_price?: { id: string; price_list_id: string; product_id: string; uom_id: string; unit_price: Decimal | null; factor_type: string; price_list?: Ref; product?: Ref; uom?: Ref }
}

export type RoundTo = 'NONE' | 'FIVE_CENTS' | 'TEN_CENTS' | 'FIFTY_CENTS' | 'WHOLE'

export type DiscountPolicy = {
  id: string
  product_id: string
  discount_allowed: boolean
  max_discount_pct: Decimal
  max_discount_amount: Decimal | null
  min_margin_pct: Decimal
  bonus_allowed: boolean
  promo_stackable: boolean
  discount_approval_pct: Decimal | null
  round_to: RoundTo
  product?: Ref
}

export type AuthorityRow = {
  role_id: number
  role: string
  authority: { id: string; max_line_discount_pct: Decimal; max_header_discount_pct: Decimal; may_override_floor: boolean } | null
}

export type CustomerPrice = {
  id: string
  customer_id: string
  product_id: string
  uom_id: string
  unit_price: Decimal
  contract_ref: string
  effective_from: string
  effective_to: string
  customer?: Ref
  product?: Ref
  uom?: Ref
  approver?: { id: number; name: string } | null
}

export const ROUND_LABEL: Record<RoundTo, string> = {
  NONE: 'No rounding (0.01)',
  FIVE_CENTS: 'Nearest 0.05',
  TEN_CENTS: 'Nearest 0.10',
  FIFTY_CENTS: 'Nearest 0.50',
  WHOLE: 'Whole shillings',
}

export const PROMO_LABEL: Record<PromotionType, string> = {
  PERCENT_OFF: 'Percent off',
  PRICE_OVERRIDE: 'Promo price',
  BUY_X_GET_Y: 'Buy X get Y free',
}

export const dateOnly = (v: string | null | undefined) => (v ? v.slice(0, 10) : '')

/** Keeps a decimal-looking string while typing. */
export const decimalInput = (v: string) => v.replace(/[^\d.]/g, '').replace(/(\..*)\./g, '$1')

/** Default unit when a product is picked: its default sales unit. */
export function defaultUomId(product: Product | null): string {
  const uoms = product?.uoms ?? []
  return (uoms.find((u) => u.is_default_sales && u.is_sales) ?? uoms.find((u) => u.is_sales) ?? uoms[0])?.uom_id ?? ''
}
