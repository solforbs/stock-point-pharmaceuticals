import { newIdempotencyKey } from '../../lib/api'
import type { NormalisedApiError } from '../../lib/apiError'
import { dAdd, dCmp, dDiv, dIsPos, dMul, dRound2, dSum, isValidDecimal } from '../../lib/decimal'
import type { PackProduct, PricePack } from '../../lib/offline/db'
import { isPackStale } from '../../lib/offline/pack'
import type { Product, Quote, QuoteLine } from '../../lib/types'
import type { CartLine } from './cartStore'

/**
 * Part 16.7 — pricing a cart with no server. The rules are deliberately
 * narrower than online: a walk-in retail sale at the price pack's prices,
 * paid in full. Anything that needs the server's judgement (a customer's
 * credit, a discount, wholesale terms) waits for the connection.
 *
 * The arithmetic mirrors PriceQuoteService line for line (net rounded to
 * the cent, VAT on the net rounded to the cent) so a replayed sale posts at
 * exactly the total the customer was shown.
 */
export const OFFLINE_QUOTE_PREFIX = 'offline:'

export function isOfflineQuote(quote: Quote | null | undefined): boolean {
  return !!quote?.quote_id?.startsWith(OFFLINE_QUOTE_PREFIX)
}

type CartFacts = {
  saleMode: string
  customer: unknown
  headerDiscount: string
  lines: CartLine[]
}

function refuse(code: string, message: string): { error: NormalisedApiError } {
  return { error: { status: 0, code, message, details: {}, errors: {} } }
}

export function buildOfflineQuote(
  cart: CartFacts,
  pack: PricePack | null | undefined,
  stock: Map<string, string> | undefined,
): { quote: Quote } | { error: NormalisedApiError } {
  if (!pack) {
    return refuse('OFFLINE_NO_PRICES', 'This till has no offline price list yet. It downloads one automatically while the server is reachable.')
  }
  if (isPackStale(pack)) {
    return refuse('OFFLINE_PRICES_STALE', `The offline price list was taken ${new Date(pack.generated_at).toLocaleString()} and has expired. Sales must wait for the connection.`)
  }
  if (cart.saleMode !== 'RETAIL') {
    return refuse('OFFLINE_RETAIL_ONLY', 'Only walk-in retail sales can be made offline. Switch to Retail, or wait for the connection.')
  }
  if (cart.customer) {
    return refuse('OFFLINE_NO_CUSTOMER', 'Customer accounts need the server (credit and pricing terms). Remove the customer to sell as walk-in.')
  }
  if (isValidDecimal(cart.headerDiscount) && dIsPos(cart.headerDiscount)) {
    return refuse('OFFLINE_NO_DISCOUNT', 'Discounts need approval limits from the server. Remove the discount to sell offline.')
  }

  const byId = new Map(pack.products.map((p) => [p.id, p]))
  const usedBase = new Map<string, string>()
  const lines: QuoteLine[] = []

  for (const line of cart.lines) {
    if (line.requestedDiscountPct && dIsPos(line.requestedDiscountPct)) {
      return refuse('OFFLINE_NO_DISCOUNT', `${line.productName}: discounts need approval limits from the server.`)
    }
    const product = byId.get(line.productId)
    const price = product?.prices[line.uomId]
    if (!product || !price) {
      return refuse('OFFLINE_NOT_IN_PRICE_LIST', `${line.productName} (${line.uomCode}) is not in the offline price list, so it cannot be sold until the connection returns.`)
    }
    if (!isValidDecimal(line.qty) || !dIsPos(line.qty)) {
      return refuse('INVALID_INPUT', `${line.productName}: enter a quantity.`)
    }
    if (product.is_discrete && !/^\d+(\.0+)?$/.test(line.qty)) {
      return refuse('INVALID_INPUT', `${line.productName} is sold in whole ${line.uomCode} only.`)
    }

    const used = dAdd(usedBase.get(product.id) ?? '0', line.qtyBase)
    usedBase.set(product.id, used)
    const left = stock?.get(product.id) ?? product.free_to_sell
    if (dCmp(used, left) > 0) {
      return refuse('INSUFFICIENT_STOCK', `${line.productName}: this till believes only ${left} ${product.base_uom?.code ?? 'units'} are left.`)
    }

    // Same rule as the server: the till may sell above the landing price, never
    // below it, and a till price is final — its VAT is inside it, not added on top.
    const taxFactor = dAdd('1', dMul(price.tax_rate, '0.01'))
    const landingPrice = dRound2(dMul(price.unit_price, taxFactor))
    const tillPrice = line.sellingPrice && isValidDecimal(line.sellingPrice) ? dRound2(line.sellingPrice) : null
    const raised = !!tillPrice && dCmp(tillPrice, landingPrice) > 0
    const unitPrice = raised && tillPrice ? dRound2(dDiv(tillPrice, taxFactor)) : price.unit_price
    const net = dRound2(dMul(unitPrice, line.qty))
    const tax = dRound2(dMul(net, dMul(price.tax_rate, '0.01')))
    lines.push({
      line_ref: line.lineRef,
      product_id: product.id,
      product_code: product.code,
      product_name: product.name,
      uom_id: line.uomId,
      uom_code: line.uomCode,
      factor_to_base: line.factorToBase,
      quantity: line.qty,
      qty_base: line.qtyBase,
      list_price: price.unit_price,
      break_price: unitPrice,
      landing_price: landingPrice,
      price_source: raised ? 'TILL_PRICE' : 'OFFLINE_PRICE_PACK',
      requested_discount_pct: null,
      unit_price: unitPrice,
      discount_per_unit: '0.0000',
      discount_pct: '0.0000',
      discount_amount: '0.0000',
      discount_source: 'NONE',
      discount_capped_by: null,
      discount_reason: null,
      line_subtotal: net,
      net_amount: net,
      tax_code_id: null,
      tax_code: price.tax_code,
      tax_rate: price.tax_rate,
      tax_amount: tax,
      line_total: dAdd(net, tax),
      bonus_qty: '0.0000',
      bonus_product_id: null,
      bonus_funded_by: null,
      unit_cost: null,
      line_cost: null,
      gross_profit: null,
      margin_pct: null,
      effective_margin_pct: null,
      floor_price: null,
      floor_breached: false,
      approval_required: false,
      batch_id: null,
      explain: [
        `Offline price list of ${new Date(pack.generated_at).toLocaleString()}`,
        ...(raised ? [`Final selling price set at the till → ${tillPrice} (landing price ${landingPrice})`] : []),
      ],
    })
  }

  const subtotal = dSum(lines.map((l) => l.net_amount))
  const tax = dSum(lines.map((l) => l.tax_amount))

  return {
    quote: {
      quote_id: `${OFFLINE_QUOTE_PREFIX}${newIdempotencyKey()}`,
      expires_at: null,
      currency: 'KES',
      sale_mode: 'RETAIL',
      customer_id: null,
      min_shelf_life_days: 0,
      lines,
      header_discount: [],
      totals: { subtotal, discount: '0.0000', net: subtotal, tax, grand_total: dAdd(subtotal, tax), total_cost: null, gross_profit: null, margin_pct: null },
      approval_required: false,
      floor_breached: false,
      quoted_at: new Date().toISOString(),
    },
  }
}

/** A pack product in the shape the cart's addProduct expects. */
export function packToProduct(p: PackProduct): Product {
  const basePrice = p.prices[p.base_uom_id]?.unit_price ?? null
  return {
    id: p.id,
    code: p.code,
    sku: p.sku,
    gtin: p.gtin,
    name: p.name,
    generic_name: p.generic_name,
    strength: p.strength,
    is_discrete: p.is_discrete,
    pack_integrity: p.pack_integrity,
    requires_batch: true,
    reorder_point: '0',
    safety_stock: '0',
    lead_time_days: 0,
    default_price: basePrice,
    is_active: true,
    base_uom_id: p.base_uom_id,
    base_uom: p.base_uom,
    uoms: p.uoms,
    stock: { on_hand: p.free_to_sell, reserved: '0', free_to_sell: p.free_to_sell, nearest_expiry: null },
  }
}
