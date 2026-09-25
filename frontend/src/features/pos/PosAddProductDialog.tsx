import { useEffect, useRef, useState } from 'react'
import { Modal } from '../../components/ui/Modal'
import { Button } from '../../components/ui/primitives'
import { QuantityInput } from '../../components/ui/QuantityInput'
import { dAdd, dCmp, dDiv, dIsPos, dMul, dMulInt, dRound2, dSub, isValidDecimal } from '../../lib/decimal'
import { formatDate } from '../../lib/format'
import { useProductInsight, useProductStock } from '../../lib/hooks'
import { formatMoney, formatPct, formatQty } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import type { Product, ProductInsight } from '../../lib/types'
import { useCartStore, type AddProductOptions } from './cartStore'
import { previewFefo } from './fefo'

const MARKUP_STEPS = ['10', '20', '25', '30']

export interface PosAddProductDialogProps {
  product: Product | null
  offline: boolean
  onClose: () => void
  onConfirm: (product: Product, uomId: string, options: AddProductOptions) => void
}

/**
 * The till's add-to-cart step: everything known about the product, and the
 * price this sale goes out at. The landing price is what the catalog charges;
 * the cashier may sell above it for this sale only, and the receipt records
 * that price times the quantity. The catalog price itself never changes.
 */
export function PosAddProductDialog({ product, offline, onClose, onConfirm }: PosAddProductDialogProps) {
  if (!product) return null
  // Keyed so every product opens on a clean form.
  return <AddProductForm key={product.id} product={product} offline={offline} onClose={onClose} onConfirm={onConfirm} />
}

function AddProductForm({ product, offline, onClose, onConfirm }: PosAddProductDialogProps & { product: Product }) {
  const canSetPrice = usePermission('sale.price.override')
  const storeId = useCartStore((s) => s.storeId)
  const saleMode = useCartStore((s) => s.saleMode)
  const customerId = useCartStore((s) => s.customer?.id ?? null)
  const lines = useCartStore((s) => s.lines)

  const salesUoms = (product.uoms ?? []).filter((u) => u.is_sales || u.is_base)
  const defaultUom = salesUoms.find((u) => u.is_default_sales) ?? salesUoms.find((u) => u.is_sales) ?? salesUoms[0]
  const [uomId, setUomId] = useState(defaultUom?.uom_id ?? '')
  const [qty, setQty] = useState('1')
  const [sellingPrice, setSellingPrice] = useState('')
  const priceRef = useRef<HTMLInputElement>(null)

  const { data: insight, isLoading: insightLoading } = useProductInsight(product.id, storeId, customerId, !offline)
  const { data: stock } = useProductStock(offline ? null : product.id)

  const uom = salesUoms.find((u) => u.uom_id === uomId) ?? defaultUom
  const factor = uom?.factor_to_base ?? 1
  const uomCode = uom?.uom?.code ?? 'unit'
  const baseUomCode = product.base_uom?.code ?? 'unit'
  const insightUom = insight?.uoms.find((u) => u.uom_id === uomId)
  const listed = saleMode === 'WHOLESALE' ? (insightUom?.trade ?? insightUom?.retail) : (insightUom?.retail ?? insightUom?.trade)
  // The final shelf price: VAT was settled at receiving, so nothing is added on top here.
  const landingPrice = listed?.gross_price ?? (product.default_price ? dMulInt(product.default_price, factor) : null)
  const inCart = lines.find((l) => l.productId === product.id && l.uomId === uomId)

  // Open on the price this line already sells at, else the landing price.
  useEffect(() => {
    const agreed = inCart?.sellingPrice ?? landingPrice
    setSellingPrice(agreed ? dRound2(agreed) : '')
    // Only when the unit or the landing price itself changes, not on every keystroke.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [uomId, landingPrice])

  useEffect(() => {
    priceRef.current?.focus()
    priceRef.current?.select()
  }, [insightLoading])

  const priceValid = isValidDecimal(sellingPrice) && dIsPos(sellingPrice)
  const belowLanding = priceValid && !!landingPrice && dCmp(sellingPrice, landingPrice) < 0
  const raised = priceValid && !!landingPrice && dCmp(sellingPrice, landingPrice) > 0
  const qtyValid = isValidDecimal(qty) && dIsPos(qty) && (!product.is_discrete || /^\d+$/.test(qty.trim()))
  const unitPrice = priceValid ? sellingPrice : landingPrice
  const total = unitPrice && qtyValid ? dRound2(dMul(unitPrice, qty)) : null
  const marginPerUnit = raised && landingPrice ? dSub(sellingPrice, landingPrice) : null
  const marginPct = marginPerUnit && landingPrice && dIsPos(landingPrice) ? dMul(dDiv(marginPerUnit, landingPrice), '100') : null
  const unitCost = insightUom?.unit_cost ?? null
  const markupOnCost = unitPrice && unitCost && dIsPos(unitCost) ? dMul(dDiv(dSub(unitPrice, unitCost), unitCost), '100') : null

  const storeRow = stock?.stores.find((row) => row.store_id === storeId)
  const freeHere = offline ? (product.stock?.free_to_sell ?? null) : (storeRow?.free_to_sell ?? null)
  const qtyBase = qtyValid ? dMulInt(qty, factor) : '0'
  const fefo = previewFefo(storeRow, qtyBase)
  const otherStores = (stock?.stores ?? []).filter((row) => row.store_id !== storeId && dIsPos(row.free_to_sell))

  const canConfirm = qtyValid && !belowLanding && !!uom && (priceValid || !canSetPrice || !landingPrice)

  function confirm() {
    if (!canConfirm || !uom) return
    // Only a price above the landing price travels; anything else sells at the catalog's.
    onConfirm(product, uom.uom_id, { qty: qty.trim(), sellingPrice: canSetPrice && raised ? dRound2(sellingPrice) : null })
  }

  function applyMarkup(pct: string) {
    if (!landingPrice) return
    setSellingPrice(dRound2(dMul(landingPrice, dAdd('1', dDiv(pct, '100')))))
  }

  return (
    <Modal
      open
      onClose={onClose}
      width="75vw"
      title={
        <span className="flex items-center gap-2 flex-wrap">
          <span className="text-base">{product.name}</span>
          {product.strength && <span className="px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-xs font-bold">{product.strength}</span>}
          <span className="text-slate-500 font-mono text-xs font-medium">#{product.code}</span>
        </span>
      }
      footer={
        <>
          <Button onClick={onClose}>Cancel</Button>
          <Button variant="primary" onClick={confirm} disabled={!canConfirm}>
            Add to cart{total ? ` · KES ${formatMoney(total)}` : ''}
          </Button>
        </>
      }
    >
      <div className="grid grid-cols-1 lg:grid-cols-5 gap-5 min-h-[62vh]">
        {/* Product facts */}
        <div className="lg:col-span-3 space-y-4">
          <section className="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <Fact label="Generic name" value={product.generic_name} />
            <Fact label="Category" value={product.category?.name} />
            <Fact label="Manufacturer" value={product.manufacturer?.name} />
            <Fact label="Dosage form" value={product.dosage_form?.name} />
            <Fact label="Storage" value={product.storage_condition?.name} />
            {product.description && (
              <div className="col-span-2 sm:col-span-3">
                <Fact label="Description" value={product.description} />
              </div>
            )}
          </section>

          <section className="ui-card p-3 space-y-1.5">
            <div className="ui-label">Stock</div>
            {freeHere === null ? (
              <div className="text-slate-500">{offline ? 'Stock is checked when the sale syncs.' : 'Checking stock…'}</div>
            ) : (
              <div className="tabular">
                <span className={`font-bold ${dIsPos(freeHere) ? 'text-emerald-700' : 'text-rose-700'}`}>
                  {formatQty(freeHere)} {baseUomCode} free to sell here
                </span>
                {factor !== 1 && dIsPos(freeHere) && <span className="text-slate-500"> ({formatQty(dDiv(freeHere, String(factor)))} {uomCode})</span>}
                {otherStores.length > 0 && (
                  <span className="text-slate-500"> · {otherStores.map((row) => `${formatQty(row.free_to_sell)} in ${row.store_code}`).join(', ')}</span>
                )}
              </div>
            )}
            {fefo.allocations.length > 0 && (
              <div className="text-slate-600">
                Comes from batch{' '}
                <span className="font-mono">{fefo.allocations.map((a) => `${a.batch_number} (exp ${formatDate(a.expiry_date)})`).join(', ')}</span>
              </div>
            )}
            {storeRow && dIsPos(fefo.shortfall) && <div className="text-rose-700 font-bold">Short by {formatQty(fefo.shortfall)} {baseUomCode} for this quantity</div>}
          </section>

          {insight && insight.uoms.length > 0 && (
            <section className="ui-card overflow-hidden">
              <div className="px-3 pt-3 ui-label">Prices per unit</div>
              <div className="overflow-x-auto">
                <table className="ui-table tabular">
                  <thead>
                    <tr>
                      <th>Unit</th>
                      {insight.buying && <th className="text-right">Landed cost</th>}
                      <th className="text-right">Retail</th>
                      <th className="text-right">Trade</th>
                    </tr>
                  </thead>
                  <tbody>
                    {insight.uoms.map((u) => (
                      <tr key={u.uom_id} className={u.uom_id === uomId ? 'font-bold bg-blue-50/40' : ''}>
                        <td>
                          {u.uom_code}
                          {u.factor_to_base !== 1 && <span className="text-slate-400 font-normal"> ×{u.factor_to_base}</span>}
                        </td>
                        {insight.buying && <td className="text-right">{u.unit_cost ? formatMoney(u.unit_cost) : '—'}</td>}
                        <td className="text-right">{u.retail ? formatMoney(u.retail.gross_price) : '—'}</td>
                        <td className="text-right">{u.trade ? formatMoney(u.trade.gross_price) : '—'}</td>                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </section>
          )}

          {insight && (insight.buying?.last_purchase || insight.usual_price.customer || insight.usual_price.everyone) && (
            <section className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              {insight.buying?.last_purchase && (
                <div className="ui-card p-3">
                  <div className="ui-label">Last bought</div>
                  <div className="font-semibold text-slate-800 tabular">
                    {formatMoney(insight.buying.last_purchase.unit_cost)} per {insight.buying.last_purchase.uom_code ?? 'unit'}
                  </div>
                  <div className="text-slate-500">
                    {insight.buying.last_purchase.supplier ?? 'Supplier'} · {formatDate(insight.buying.last_purchase.received_at)}
                  </div>
                </div>
              )}
              {(insight.usual_price.customer ?? insight.usual_price.everyone) && (
                <UsualPrice insight={insight} />
              )}
            </section>
          )}
        </div>

        {/* The sale decision */}
        <div className="lg:col-span-2 ui-card p-4 space-y-4 self-start">
          <div className="grid grid-cols-2 gap-3">
            <label className="block">
              <span className="ui-label">Unit</span>
              <select
                value={uomId}
                onChange={(e) => setUomId(e.target.value)}
                disabled={salesUoms.length <= 1}
                className="mt-1 w-full h-9 px-2.5 rounded-xl bg-white border border-slate-200 text-sm font-bold text-slate-800 focus:outline-none focus:border-blue-500"
              >
                {salesUoms.map((u) => (
                  <option key={u.uom_id} value={u.uom_id}>
                    {u.uom?.code ?? u.uom_id} {u.factor_to_base !== 1 ? `(×${u.factor_to_base})` : ''}
                  </option>
                ))}
              </select>
            </label>
            <label className="block">
              <span className="ui-label">Quantity</span>
              <div className="mt-1">
                <QuantityInput
                  value={qty}
                  onChange={setQty}
                  uomCode={uomCode}
                  factorToBase={factor}
                  baseUomCode={baseUomCode}
                  isDiscrete={product.is_discrete}
                  max={freeHere}
                  onEnter={confirm}
                />
              </div>
            </label>
          </div>

          <div className="flex items-baseline justify-between rounded-xl bg-slate-50 border border-slate-200/70 px-3 py-2">
            <span className="text-slate-600 font-semibold">Landing price / {uomCode}</span>
            <span className="text-base font-bold text-slate-900 tabular">
              {landingPrice ? formatMoney(landingPrice) : insightLoading ? '…' : '—'}
            </span>
          </div>

          <div>
            <label className="block">
              <span className="ui-label">Final selling price / {uomCode}</span>
              <input
                ref={priceRef}
                type="text"
                inputMode="decimal"
                value={sellingPrice}
                disabled={!canSetPrice}
                onChange={(e) => setSellingPrice(e.target.value.replace(/[^\d.]/g, ''))}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') {
                    e.preventDefault()
                    confirm()
                  }
                }}
                className={`mt-1 w-full h-12 px-3 rounded-xl border text-2xl font-bold text-right tabular focus:outline-none ${
                  belowLanding ? 'border-rose-400 text-rose-700 bg-rose-50/40' : 'border-slate-300 text-slate-900 focus:border-blue-500'
                } disabled:bg-slate-50 disabled:text-slate-500`}
              />
            </label>
            {canSetPrice ? (
              <div className="flex flex-wrap items-center gap-1.5 mt-2">
                {MARKUP_STEPS.map((pct) => (
                  <button
                    key={pct}
                    type="button"
                    disabled={!landingPrice}
                    onClick={() => applyMarkup(pct)}
                    className="px-2.5 py-1 rounded-full bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200/60 text-xs font-bold cursor-pointer disabled:opacity-40"
                  >
                    +{pct}%
                  </button>
                ))}
                <button
                  type="button"
                  disabled={!landingPrice}
                  onClick={() => landingPrice && setSellingPrice(dRound2(landingPrice))}
                  className="px-2.5 py-1 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold cursor-pointer disabled:opacity-40"
                >
                  Landing price
                </button>
              </div>
            ) : (
              <p className="mt-1.5 text-slate-500">Sells at the landing price. Setting a different price needs the price override permission.</p>
            )}
            {belowLanding && (
              <p role="alert" className="mt-1.5 text-rose-700 font-semibold">
                Below the landing price. To sell for less, add it at the landing price and give a discount in the cart.
              </p>
            )}
          </div>

          <dl className="space-y-1.5 tabular border-t border-slate-100 pt-3">
            <Row label={`${qtyValid ? formatQty(qty) : '—'} ${uomCode} × ${unitPrice ? formatMoney(unitPrice) : '—'}`} value={total ? formatMoney(total) : '—'} />
            <Row label="To pay" value={total ? `KES ${formatMoney(total)}` : '—'} strong />
            {marginPerUnit && (
              <Row
                label="Above landing price"
                value={`+${formatMoney(marginPerUnit)} / ${uomCode}${marginPct ? ` (+${formatPct(marginPct)})` : ''}`}
                tone="text-emerald-700"
              />
            )}
            {markupOnCost && <Row label="Mark-up on landed cost" value={formatPct(markupOnCost)} />}
          </dl>

          {inCart && (
            <p className="text-slate-500">
              Already in the cart: {formatQty(inCart.qty)} {uomCode}. This adds {qtyValid ? formatQty(qty) : 'to it'} and prices the whole line at the price above.
            </p>
          )}
          <p className="text-slate-400">The catalog price is not changed. The server confirms the final price before payment.</p>
        </div>
      </div>
    </Modal>
  )
}

function Fact({ label, value }: { label: string; value: string | null | undefined }) {
  return (
    <div>
      <div className="ui-label">{label}</div>
      <div className="text-sm font-semibold text-slate-800">{value || '—'}</div>
    </div>
  )
}

function Row({ label, value, strong, tone }: { label: string; value: string; strong?: boolean; tone?: string }) {
  return (
    <div className={`flex items-baseline justify-between gap-3 ${strong ? 'text-base font-bold text-slate-900' : 'text-slate-600'} ${tone ?? ''}`}>
      <dt>{label}</dt>
      <dd className="font-bold">{value}</dd>
    </div>
  )
}

function UsualPrice({ insight }: { insight: ProductInsight }) {
  const usual = insight.usual_price.customer ?? insight.usual_price.everyone
  if (!usual) return null
  return (
    <div className="ui-card p-3">
      <div className="ui-label">{insight.usual_price.customer ? 'This customer usually pays' : 'Usually sells at'}</div>
      <div className="font-semibold text-slate-800 tabular">
        {formatMoney(usual.unit_price)} per {usual.uom_code ?? 'unit'}
      </div>
      <div className="text-slate-500">
        {usual.times}× in six months · last {formatMoney(usual.last_price)} on {formatDate(usual.last_sold_at)}
      </div>
    </div>
  )
}
