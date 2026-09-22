/**
 * A distributor's terms: a gross trade price less a purchase discount.
 * The net cost is trade × (1 − discount%), rounded to the cent — the same
 * rule the server applies when the cost is left blank.
 */
export function netFromTrade(tradePrice: string, discountPct: string): string {
  const trade = Number(tradePrice)
  if (tradePrice === '' || !Number.isFinite(trade)) return ''
  const discount = discountPct === '' ? 0 : Number(discountPct)
  if (!Number.isFinite(discount)) return ''
  return (Math.round(trade * (1 - discount / 100) * 100) / 100).toFixed(2)
}

/** "450.00 less 7%" — or null when no trade price was captured. */
export function tradeTermsLabel(tradePrice: string | null | undefined, discountPct: string | null | undefined): string | null {
  if (tradePrice == null || tradePrice === '') return null
  const trade = Number(tradePrice).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
  const discount = Number(discountPct ?? 0)
  return discount > 0 ? `Trade ${trade} less ${discount}%` : `Trade ${trade}`
}

/** Strips anything that is not a digit or a decimal point. */
export function decimalInput(value: string): string {
  return value.replace(/[^\d.]/g, '')
}
