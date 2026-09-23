import { ChevronDown, ChevronRight, Plus, Repeat2 } from 'lucide-react'
import { useState } from 'react'
import { dCmp, dDiv, dIsPos, dMul, dSub } from '../../lib/decimal'
import { formatDate } from '../../lib/format'
import { useProductInsight } from '../../lib/hooks'
import { formatMoney, formatPct, formatQty } from '../../lib/money'
import type { Product, ProductInsight, ProductStock } from '../../lib/types'
import type { CartLine } from './cartStore'

const TAX_CHIP: Record<ProductInsight['tax']['treatment'], { label: (rate: string) => string; className: string; title: string }> = {
  STANDARD: { label: (rate) => `VAT ${Number(rate)}%`, className: 'bg-blue-50 text-blue-700 border-blue-200', title: 'Standard-rated: VAT is added to the price' },
  ZERO_RATED: { label: () => 'Zero-rated', className: 'bg-emerald-50 text-emerald-700 border-emerald-200', title: 'Zero-rated: taxable at 0%' },
  EXEMPT: { label: () => 'VAT exempt', className: 'bg-slate-100 text-slate-700 border-slate-200', title: 'Exempt from VAT' },
  NOT_SET: { label: () => 'Tax not set', className: 'bg-amber-50 text-amber-800 border-amber-200', title: 'No tax code on this product yet: set it on the product card' },
}

/**
 * The facts a cashier weighs beside a cart line (buying price, trade and
 * retail per unit and per pack, VAT treatment, the price never to go below,
 * what this customer usually pays) and in-stock substitutes when the shelf
 * is short.
 */
export function CartLineInsight({
  line,
  storeId,
  customerId,
  stock,
  short,
  offline,
  canUsePrice,
  onUsePrice,
  onAddAlternative,
}: {
  line: CartLine
  storeId: string | null
  customerId: string | null
  stock: ProductStock | undefined
  /** The selling store cannot cover the quantity. */
  short: boolean
  offline: boolean
  canUsePrice: boolean
  onUsePrice: (unitPrice: string, reason: string) => void
  onAddAlternative: (product: Product) => void
}) {
  const [pricesOpen, setPricesOpen] = useState(false)
  const [altOpen, setAltOpen] = useState(false)
  const { data: insight } = useProductInsight(line.productId, storeId, customerId, !offline)

  if (offline || !insight) return null

  const current = insight.uoms.find((u) => u.uom_id === line.uomId)
  const tax = TAX_CHIP[insight.tax.treatment]
  const lastPurchase = insight.buying?.last_purchase ?? null
  const usual = insight.usual_price.customer ?? insight.usual_price.everyone
  const usualIsCustomer = !!insight.usual_price.customer
  const usualMatchesUnit = usual?.uom_id === line.uomId
  const retailNow = current?.retail?.unit_price ?? null
  const canApplyUsual = canUsePrice && usualMatchesUnit && !!usual && !!retailNow && dCmp(usual.unit_price, retailNow) < 0
  const stores = (stock?.stores ?? []).filter((row) => dIsPos(row.free_to_sell))
  const showAlternatives = insight.alternatives.length > 0 && (short || altOpen)
  // The pricing model, in the client's own columns: what is on the shelf, what
  // we paid for it, what it lists at and what this line is actually going out at.
  const storeRow = (stock?.stores ?? []).find((row) => row.store_id === storeId)
  const freeToSell = storeRow?.free_to_sell ?? null
  const tradePerBase = lastPurchase?.trade_price_per_base ?? null
  const soldAt = line.quoted?.unit_price ?? line.estimateUnitPrice
  const discounted = !!line.quoted && dIsPos(line.quoted.discount_per_unit)
  const lineCost = current?.unit_cost ?? null
  const soldAtMarkupPct = soldAt && lineCost && dIsPos(lineCost) ? dMul(dDiv(dSub(soldAt, lineCost), lineCost), '100') : null
  const floorPrice = current?.retail?.floor_price ?? null

  return (
    <div className="mt-2 pt-1.5 border-t border-slate-100/80 space-y-1.5" onClick={(e) => e.stopPropagation()}>
      <div className="flex flex-wrap items-center gap-1 text-[11px] tabular">
        <span title={`${tax.title}${insight.tax.code ? ` (${insight.tax.code})` : ''}`} className={`px-1.5 py-0.5 rounded-md border font-bold ${tax.className}`}>
          {tax.label(insight.tax.rate_pct)}
        </span>

        <span
          className={`px-1.5 py-0.5 rounded-md border font-semibold ${stores.length ? 'bg-slate-50 text-slate-700 border-slate-200' : 'bg-rose-50 text-rose-700 border-rose-200'}`}
          title="Free-to-sell stock in each store of this branch"
        >
          {stores.length
            ? `Stock: ${stores.map((row) => `${row.store_code} ${formatQty(row.free_to_sell)}`).join(' · ')} ${line.baseUomCode}`
            : 'Out of stock in this branch'}
        </span>

        {lastPurchase && (
          <span
            className="px-1.5 py-0.5 rounded-md border bg-violet-50 text-violet-800 border-violet-200 font-semibold"
            title={`Last bought ${formatDate(lastPurchase.received_at)} on ${lastPurchase.grn_number} at ${formatMoney(lastPurchase.unit_cost)} per ${lastPurchase.uom_code ?? 'unit'}`}
          >
            Buying {current?.unit_cost ? formatMoney(current.unit_cost) : formatMoney(lastPurchase.unit_cost_per_base)}
            {lastPurchase.supplier ? ` · ${lastPurchase.supplier}` : ''}
          </span>
        )}

        {lastPurchase?.trade_price && (
          <span
            className="px-1.5 py-0.5 rounded-md border bg-violet-50 text-violet-800 border-violet-200 font-semibold"
            title={`What ${lastPurchase.supplier ?? 'the supplier'} quoted on ${lastPurchase.grn_number}: trade price less their discount gave ${formatMoney(lastPurchase.unit_cost)} per ${lastPurchase.uom_code ?? 'unit'}`}
          >
            Supplier trade {formatMoney(lastPurchase.trade_price)}/{lastPurchase.uom_code ?? 'unit'}
            {Number(lastPurchase.discount_pct ?? 0) > 0 && ` less ${Number(lastPurchase.discount_pct)}%`}
          </span>
        )}

        {current?.trade && (
          <span className="px-1.5 py-0.5 rounded-md border bg-slate-50 text-slate-700 border-slate-200 font-semibold" title="Trade (wholesale list) price, before VAT">
            Trade {formatMoney(current.trade.unit_price)}
          </span>
        )}

        {current?.retail && (
          <span className="px-1.5 py-0.5 rounded-md border bg-slate-50 text-slate-700 border-slate-200 font-semibold" title="Suggested retail price, before VAT, then including VAT">
            Retail {formatMoney(current.retail.unit_price)}
            {dCmp(current.retail.gross_price, current.retail.unit_price) !== 0 && <span className="text-slate-500"> ({formatMoney(current.retail.gross_price)} incl.)</span>}
            {current.retail_markup_pct && <span className="text-slate-500"> · +{formatPct(current.retail_markup_pct)}</span>}
          </span>
        )}

        {current?.retail?.floor_price && (
          <span
            className="px-1.5 py-0.5 rounded-md border bg-rose-50 text-rose-700 border-rose-200 font-bold"
            title={`The margin floor${insight.min_margin_pct ? ` (${formatPct(insight.min_margin_pct)} minimum margin)` : ''}: discounts stop here unless a manager overrides`}
          >
            Never below {formatMoney(current.retail.floor_price)}
          </span>
        )}

        {usual && (
          <span
            className="px-1.5 py-0.5 rounded-md border bg-amber-50 text-amber-800 border-amber-200 font-semibold inline-flex items-center gap-1"
            title={`Most frequent price ${usualIsCustomer ? 'this customer paid' : 'this sold at'} in the last six months (${usual.times}×); last ${formatMoney(usual.last_price)} on ${formatDate(usual.last_sold_at)}`}
          >
            {usualIsCustomer ? 'Usually pays' : 'Usually sells at'} {formatMoney(usual.unit_price)}/{usual.uom_code}
            {canApplyUsual && (
              <button
                type="button"
                onClick={() => onUsePrice(usual.unit_price, usualIsCustomer ? 'Customer usual price' : 'Usual selling price')}
                className="ml-0.5 underline font-bold hover:text-amber-900 cursor-pointer"
              >
                use
              </button>
            )}
          </span>
        )}
      </div>

      <div className="flex items-center gap-3 text-[11px] font-semibold">
        {insight.uoms.length > 0 && (
          <button type="button" onClick={() => setPricesOpen((v) => !v)} className="inline-flex items-center gap-1 text-slate-600 hover:text-slate-900 cursor-pointer">
            {pricesOpen ? <ChevronDown size={12} /> : <ChevronRight size={12} />} Pricing model
          </button>
        )}
        {insight.alternatives.length > 0 && !short && (
          <button type="button" onClick={() => setAltOpen((v) => !v)} className="inline-flex items-center gap-1 text-slate-600 hover:text-slate-900 cursor-pointer">
            <Repeat2 size={12} /> Alternatives ({insight.alternatives.length})
          </button>
        )}
      </div>

      {pricesOpen && (
        <div className="bg-slate-50 rounded-lg border border-slate-100 overflow-hidden">
          <table className="w-full text-[10.5px] tabular">
            <thead>
              <tr className="text-slate-500 text-left border-b border-slate-200">
                <th className="px-2 py-1 font-semibold">Unit / whole</th>
                <th className="px-2 py-1 font-semibold text-right">Stock available</th>
                <th className="px-2 py-1 font-semibold text-right">Qty</th>
                {insight.buying && <th className="px-2 py-1 font-semibold text-right">Trade buying</th>}
                {insight.buying && <th className="px-2 py-1 font-semibold text-right">Landed cost</th>}
                <th className="px-2 py-1 font-semibold text-right">Sale price</th>
                <th className="px-2 py-1 font-semibold text-right">Sold at</th>
                {insight.buying && <th className="px-2 py-1 font-semibold text-right">Mark-up</th>}
              </tr>
            </thead>
            <tbody>
              {insight.uoms.map((u) => {
                const isLine = u.uom_id === line.uomId
                const available = freeToSell !== null ? dDiv(freeToSell, String(u.factor_to_base)) : null
                const tradeBuying = tradePerBase ? dMul(tradePerBase, String(u.factor_to_base)) : null
                return (
                  <tr key={u.uom_id} className={isLine ? 'font-bold text-slate-900 bg-white' : 'text-slate-700'}>
                    <td className="px-2 py-0.5">
                      {u.uom_code}
                      {u.factor_to_base !== 1 && <span className="text-slate-400 font-normal"> ×{u.factor_to_base}</span>}
                    </td>
                    <td className="px-2 py-0.5 text-right">{available !== null ? formatQty(available) : '—'}</td>
                    <td className="px-2 py-0.5 text-right">{isLine ? formatQty(line.qty) : ''}</td>
                    {insight.buying && <td className="px-2 py-0.5 text-right">{tradeBuying ? formatMoney(tradeBuying) : '—'}</td>}
                    {insight.buying && <td className="px-2 py-0.5 text-right">{u.unit_cost ? formatMoney(u.unit_cost) : '—'}</td>}
                    <td className="px-2 py-0.5 text-right">
                      {u.retail ? formatMoney(u.retail.unit_price) : '—'}
                      {u.retail && dCmp(u.retail.gross_price, u.retail.unit_price) !== 0 && (
                        <span className="text-slate-500 font-normal"> ({formatMoney(u.retail.gross_price)} incl.)</span>
                      )}
                    </td>
                    <td className="px-2 py-0.5 text-right">
                      {isLine && soldAt ? (
                        <span className={discounted ? 'text-amber-700' : ''}>{formatMoney(soldAt)}</span>
                      ) : (
                        '—'
                      )}
                    </td>
                    {insight.buying && (
                      <td className="px-2 py-0.5 text-right">
                        {isLine && soldAtMarkupPct ? formatPct(soldAtMarkupPct) : u.retail_markup_pct ? <span className="font-normal text-slate-500">{formatPct(u.retail_markup_pct)}</span> : '—'}
                      </td>
                    )}
                  </tr>
                )
              })}
            </tbody>
          </table>
          <div className="px-2 py-1 text-[10px] text-slate-500 border-t border-slate-200 bg-white/60">
            Landed cost is the buying price plus direct expenses. Mark-up on the line being sold is measured against it.
            {floorPrice && <span className="text-rose-700 font-semibold"> Never below {formatMoney(floorPrice)}.</span>}
          </div>
        </div>
      )}

      {showAlternatives && (
        <div className="p-2 rounded-lg bg-emerald-50/60 border border-emerald-200/70 space-y-1">
          <div className="text-[10.5px] font-bold uppercase tracking-wider text-emerald-800">
            {short ? 'Short on the shelf: in-stock alternatives' : 'In-stock alternatives'}
          </div>
          {insight.alternatives.map((alt) => (
            <div key={alt.product.id} className="flex items-center justify-between gap-2 text-[11.5px]">
              <div className="min-w-0">
                <span className="font-bold text-slate-900">{alt.product.name}</span>
                {alt.product.strength && <span className="ml-1 text-slate-600">{alt.product.strength}</span>}
                <span className="ml-1.5 text-[10.5px] text-slate-500">
                  {alt.match === 'SAME_GENERIC' ? 'same molecule' : 'same category'} · {formatQty(alt.free_in_store)} here
                  {dCmp(alt.free_in_branch, alt.free_in_store) > 0 && `, ${formatQty(alt.free_in_branch)} in branch`}
                </span>
              </div>
              <button
                type="button"
                onClick={() => onAddAlternative(alt.product)}
                className="h-6 px-2 rounded-md bg-white border border-emerald-300 text-emerald-700 hover:bg-emerald-600 hover:text-white text-[11px] font-bold inline-flex items-center gap-0.5 shrink-0 cursor-pointer"
              >
                <Plus size={11} /> Add
              </button>
            </div>
          ))}
        </div>
      )}
    </div>
  )
}
