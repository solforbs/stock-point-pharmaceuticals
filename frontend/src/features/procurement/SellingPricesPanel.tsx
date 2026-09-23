import { useQuery } from '@tanstack/react-query'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { apiGet } from '../../lib/api'
import type { SellingPrices } from '../../lib/types'
import { decimalInput } from './tradeTerms'

/** The house mark-up band on landed cost, as agreed with the client. */
const MARKUP_BAND = [18, 20]

type Props = {
  productId: string
  uomId: string
  uomCode: string
  /** The new buying cost per received unit, as keyed on the receipt line. */
  buyingCost: string
  values: Record<string, string>
  onChange: (values: Record<string, string>) => void
}

/**
 * Per goods receipt line: the product's price on every active price list in
 * the received unit, the margin each leaves over the new buying cost, and a
 * box to set a new price. Prices keyed here are written when the receipt posts.
 */
export function SellingPricesPanel({ productId, uomId, uomCode, buyingCost, values, onChange }: Props) {
  const prices = useQuery({
    queryKey: ['selling-prices', productId, uomId],
    queryFn: () => apiGet<SellingPrices>(`/api/products/${productId}/selling-prices`, { uom_id: uomId }),
  })

  if (prices.isLoading) return <LoadingSkeleton rows={2} />
  if (prices.isError) return <InlineError error={prices.error} />
  const data = prices.data
  if (!data) return null

  const cost = Number(buyingCost)
  const taxRate = Number(data.tax_rate_pct)

  /** The house band: landed cost plus 18–20%, grossed up where the list is quoted incl. VAT. */
  function markedUp(pct: number, includesTax: boolean): string | null {
    if (!(cost > 0)) return null
    const net = cost * (1 + pct / 100)
    return (includesTax ? net * (1 + taxRate / 100) : net).toFixed(2)
  }

  /** Margin on the selling price, before VAT: (price − cost) ÷ price. */
  function marginPct(price: string | null | undefined, includesTax: boolean): number | null {
    if (price == null || price === '' || !(cost > 0)) return null
    const net = includesTax ? Number(price) / (1 + taxRate / 100) : Number(price)
    if (!(net > 0)) return null
    return ((net - cost) / net) * 100
  }

  return (
    <div className="py-2 space-y-2">
      <div className="text-xs text-slate-600">
        Selling prices per <strong>{uomCode || 'unit'}</strong>. Buying cost <strong className="tabular">KES {cost > 0 ? cost.toFixed(2) : '—'}</strong>. New prices are saved when the receipt posts; leave a box blank to keep the current price. The +18% and +20% buttons price off the landed cost.
      </div>
      {data.lists.length === 0 ? (
        <p className="text-xs text-slate-500">There are no active price lists. Set them up under Pricing first.</p>
      ) : (
        <table className="ui-table">
          <thead>
            <tr>
              <th>Price list</th>
              <th className="text-right">Current price</th>
              <th className="text-right">Margin now</th>
              <th className="text-right">New price (KES)</th>
              <th className="text-right">New margin</th>
            </tr>
          </thead>
          <tbody>
            {data.lists.map(({ price_list: list, current }) => {
              const fixed = current?.factor_type === 'FIXED' ? current.unit_price : null
              const typed = values[list.id] ?? ''
              return (
                <tr key={list.id}>
                  <td>
                    <div className="font-semibold text-slate-900">{list.name}</div>
                    <div className="text-xs text-slate-500">
                      {[list.code, list.tier?.name ? `Tier ${list.tier.name}` : list.sale_mode, list.branch?.code, list.prices_include_tax ? 'incl. VAT' : null].filter(Boolean).join(' · ')}
                    </div>
                  </td>
                  <td className="text-right">
                    {current == null ? <span className="text-xs text-slate-400">Not set</span> : fixed != null ? <MoneyCell value={fixed} /> : <span className="text-xs text-slate-600">{formulaLabel(current.factor_type, current.factor_value)}</span>}
                  </td>
                  <td className="text-right"><MarginBadge pct={marginPct(fixed, list.prices_include_tax)} /></td>
                  <td className="text-right">
                    <input
                      value={typed}
                      placeholder={fixed != null ? String(Number(fixed)) : '0.00'}
                      onChange={(e) => onChange({ ...values, [list.id]: decimalInput(e.target.value) })}
                      className="ui-input h-7 w-24 tabular text-right font-bold"
                      aria-label={`New price on ${list.name}`}
                    />
                    {cost > 0 && (
                      <div className="flex items-center justify-end gap-1 mt-1">
                        {MARKUP_BAND.map((pct) => (
                          <button
                            key={pct}
                            type="button"
                            onClick={() => onChange({ ...values, [list.id]: markedUp(pct, list.prices_include_tax) ?? '' })}
                            title={`Landed cost plus ${pct}%${list.prices_include_tax ? ', including VAT' : ''}`}
                            className="px-1.5 h-5 rounded border border-slate-300 bg-white text-[10px] font-bold text-slate-600 hover:border-blue-400 hover:text-blue-700 cursor-pointer"
                          >
                            +{pct}%
                          </button>
                        ))}
                      </div>
                    )}
                  </td>
                  <td className="text-right"><MarginBadge pct={marginPct(typed, list.prices_include_tax)} /></td>
                </tr>
              )
            })}
          </tbody>
        </table>
      )}
    </div>
  )
}

function MarginBadge({ pct }: { pct: number | null }) {
  if (pct == null) return <span className="text-xs text-slate-400">—</span>
  const tone = pct < 0 ? 'text-rose-600' : pct < 10 ? 'text-amber-600' : 'text-emerald-600'
  return <span className={`tabular text-xs font-bold ${tone}`}>{pct.toFixed(1)}%</span>
}

function formulaLabel(factorType: string, factorValue: string | null): string {
  const pct = (Number(factorValue ?? 0) * 100).toFixed(1).replace(/\.0$/, '')
  switch (factorType) {
    case 'COST_PLUS_MARKUP':
      return `Cost + ${pct}%`
    case 'TARGET_MARGIN':
      return `${pct}% margin on cost`
    case 'LIST_RELATIVE':
      return `${pct}% of list price`
    default:
      return factorType
  }
}
