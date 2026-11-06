import { Trash2 } from 'lucide-react'
import { defaultSalesUom, ProductSearch } from '../../components/ProductSearch'
import { QuantityInput } from '../../components/ui/QuantityInput'
import { Button } from '../../components/ui/primitives'
import { dCmp, dIsPos, dMul, dMulInt, dRound2, isValidDecimal } from '../../lib/decimal'
import { useProductInsight } from '../../lib/hooks'
import { formatMoney } from '../../lib/money'
import type { Product, ProductUom } from '../../lib/types'

export type EditableLine = {
  key: string
  product: Product
  uoms: ProductUom[]
  uom_id: string
  quantity: string
  requested_discount_pct: string
  requested_discount_reason: string
  /** The final unit price (VAT inclusive) the creator decided on; empty keeps the product price. */
  unit_price: string
}

/** Where the line is priced from: shows each product's price and lets the creator set the final one. */
export type LinePricing = { storeId: string; customerId: string | null }

let counter = 0

export function lineFromProduct(product: Product, purchase = false): EditableLine | null {
  const uoms = (product.uoms ?? []).filter((u) => (purchase ? u.is_purchase || u.is_base : u.is_sales || u.is_base))
  const chosen = purchase ? (uoms.find((u) => u.is_purchase) ?? uoms[0]) : defaultSalesUom(product)
  if (!chosen) return null
  counter += 1
  return { key: `${product.id}-${counter}`, product, uoms, uom_id: chosen.uom_id, quantity: '1', requested_discount_pct: '', requested_discount_reason: '', unit_price: '' }
}

/**
 * The product's price for the line's unit — what the server would quote this
 * customer at this store (trade, else retail) — falling back to the price list.
 */
function useLineProductPrice(line: EditableLine, pricing: LinePricing) {
  const { data, isLoading } = useProductInsight(line.product.id, pricing.storeId, pricing.customerId)
  const factor = line.uoms.find((u) => u.uom_id === line.uom_id)?.factor_to_base ?? 1
  const insightUom = data?.uoms.find((u) => u.uom_id === line.uom_id)
  const listed = insightUom?.trade ?? insightUom?.retail
  const productPrice = listed?.gross_price ?? (line.product.default_price && dIsPos(line.product.default_price) ? dMulInt(line.product.default_price, factor) : null)
  const unitCost = insightUom?.unit_cost ?? null

  const typed = line.unit_price.trim()
  const typedValid = typed !== '' && isValidDecimal(typed) && dIsPos(typed)
  const finalPrice = typedValid ? typed : productPrice
  const qtyValid = isValidDecimal(line.quantity) && dIsPos(line.quantity)
  const total = finalPrice && qtyValid ? dRound2(dMul(finalPrice, line.quantity)) : null
  const belowCost = typedValid && !!unitCost && dCmp(typed, unitCost) < 0
  const changed = typedValid && !!productPrice && dCmp(typed, productPrice) !== 0

  return { productPrice, isLoading, total, belowCost, changed, unitCost }
}

function UnitPriceInput({ line, productPrice, belowCost, changed, disabled, onPrice, className = '' }: {
  line: EditableLine
  productPrice: string | null
  belowCost: boolean
  changed: boolean
  disabled?: boolean
  onPrice: (unitPrice: string) => void
  className?: string
}) {
  return (
    <input
      type="text"
      inputMode="decimal"
      aria-label={`Unit price for ${line.product.name}`}
      value={line.unit_price}
      disabled={disabled}
      placeholder={productPrice ? dRound2(productPrice) : 'Price'}
      onFocus={(e) => {
        // Start from the product price so the creator adjusts it rather than retyping it.
        if (line.unit_price === '' && productPrice) {
          onPrice(dRound2(productPrice))
          const input = e.currentTarget
          requestAnimationFrame(() => input.select())
        }
      }}
      onChange={(e) => onPrice(e.target.value.replace(/[^\d.]/g, ''))}
      className={`h-8 px-2 rounded-lg border text-xs font-bold tabular text-right outline-none ${
        belowCost ? 'border-rose-400 bg-rose-50 text-rose-700' : changed ? 'border-blue-400 bg-blue-50/60 text-blue-800' : 'border-slate-200 bg-slate-50 text-slate-800'
      } ${className}`}
    />
  )
}

function belowCostNote(unitCost: string | null) {
  return `Below cost${unitCost ? ` (${formatMoney(unitCost)})` : ''}`
}

/** Desktop row cells: product price, the creator's unit price, and the line total. */
function LinePriceCells({ line, pricing, disabled, onPrice }: { line: EditableLine; pricing: LinePricing; disabled?: boolean; onPrice: (unitPrice: string) => void }) {
  const { productPrice, isLoading, total, belowCost, changed, unitCost } = useLineProductPrice(line, pricing)
  return (
    <>
      <td className="text-right tabular text-xs text-slate-600 whitespace-nowrap">{productPrice ? formatMoney(productPrice) : isLoading ? '…' : '—'}</td>
      <td>
        <UnitPriceInput line={line} productPrice={productPrice} belowCost={belowCost} changed={changed} disabled={disabled} onPrice={onPrice} className="w-24" />
        {belowCost && <div className="text-[11px] font-semibold text-rose-700 mt-0.5">{belowCostNote(unitCost)}</div>}
      </td>
      <td className="text-right tabular text-xs font-bold text-slate-900 whitespace-nowrap">{total ? formatMoney(total) : '—'}</td>
    </>
  )
}

/** Mobile card row: the same three facts stacked. */
function LinePriceRow({ line, pricing, disabled, onPrice }: { line: EditableLine; pricing: LinePricing; disabled?: boolean; onPrice: (unitPrice: string) => void }) {
  const { productPrice, isLoading, total, belowCost, changed, unitCost } = useLineProductPrice(line, pricing)
  return (
    <div className="pt-1 border-t border-slate-100 space-y-1">
      <div className="grid grid-cols-3 gap-2 items-end">
        <div>
          <div className="text-[11px] text-slate-500 font-semibold">Product price</div>
          <div className="h-8 flex items-center text-xs tabular text-slate-700">{productPrice ? formatMoney(productPrice) : isLoading ? '…' : '—'}</div>
        </div>
        <div>
          <div className="text-[11px] text-slate-500 font-semibold">Unit price</div>
          <UnitPriceInput line={line} productPrice={productPrice} belowCost={belowCost} changed={changed} disabled={disabled} onPrice={onPrice} className="w-full" />
        </div>
        <div className="text-right">
          <div className="text-[11px] text-slate-500 font-semibold">Total</div>
          <div className="h-8 flex items-center justify-end text-xs tabular font-bold text-slate-900">{total ? formatMoney(total) : '—'}</div>
        </div>
      </div>
      {belowCost && <div className="text-[11px] font-semibold text-rose-700">{belowCostNote(unitCost)}</div>}
    </div>
  )
}

/** Shared line editor for quotations and sales orders: product, UOM, qty, discount, and (with pricing) the unit price. */
export function DocLinesEditor({
  lines,
  onChange,
  canDiscount,
  disabled,
  pricing,
}: {
  lines: EditableLine[]
  onChange: (lines: EditableLine[]) => void
  canDiscount: boolean
  disabled?: boolean
  pricing?: LinePricing | null
}) {
  function update(key: string, patch: Partial<EditableLine>) {
    onChange(lines.map((l) => (l.key === key ? { ...l, ...patch } : l)))
  }

  return (
    <div className="space-y-2">
      <ProductSearch
        disabled={disabled}
        onSelect={(product) => {
          const line = lineFromProduct(product)
          if (line) onChange([...lines, line])
        }}
      />
      {lines.length === 0 ? (
        <div className="p-6 text-center rounded-2xl border border-dashed border-slate-200 bg-slate-50/60 my-2">
          <p className="text-xs font-bold text-slate-600">No items added to quotation yet</p>
          <p className="text-xs text-slate-400 mt-0.5">Use the search bar above to select medicines by name, SKU, or barcode.</p>
        </div>
      ) : (
        <>
          {/* Desktop Table View */}
          <div className="hidden md:block overflow-x-auto rounded-xl border border-slate-200">
            <table className="ui-table">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>UOM</th>
                  <th>Qty</th>
                  {pricing && <th className="text-right">Product price</th>}
                  {pricing && <th>Unit price</th>}
                  {pricing && <th className="text-right">Total</th>}
                  {canDiscount && <th>Discount %</th>}
                  {canDiscount && <th>Discount Reason</th>}
                  <th className="w-10" />
                </tr>
              </thead>
              <tbody>
                {lines.map((line) => {
                  const uom = line.uoms.find((u) => u.uom_id === line.uom_id)
                  return (
                    <tr key={line.key}>
                      <td>
                        <div className="font-bold text-sm text-slate-900">{line.product.name}</div>
                        <div className="text-xs text-slate-500 font-mono mt-0.5">
                          #{line.product.code} {line.product.strength ? `· ${line.product.strength}` : ''}
                        </div>
                      </td>
                      <td>
                        <select
                          value={line.uom_id}
                          disabled={disabled}
                          onChange={(e) => update(line.key, { uom_id: e.target.value, unit_price: '' })}
                          className="h-8 px-2 rounded-lg bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800 outline-none"
                        >
                          {line.uoms.map((u) => (
                            <option key={u.uom_id} value={u.uom_id}>
                              {u.uom?.code} {u.factor_to_base !== 1 ? `(×${u.factor_to_base})` : ''}
                            </option>
                          ))}
                        </select>
                      </td>
                      <td>
                        <QuantityInput
                          value={line.quantity}
                          onChange={(quantity) => update(line.key, { quantity })}
                          uomCode=""
                          factorToBase={uom?.factor_to_base ?? 1}
                          baseUomCode={line.product.base_uom?.code ?? 'unit'}
                          isDiscrete={line.product.is_discrete}
                          compact
                          disabled={disabled}
                        />
                      </td>
                      {pricing && <LinePriceCells line={line} pricing={pricing} disabled={disabled} onPrice={(unit_price) => update(line.key, { unit_price })} />}
                      {canDiscount && (
                        <td>
                          <input
                            type="text"
                            inputMode="decimal"
                            value={line.requested_discount_pct}
                            disabled={disabled}
                            onChange={(e) => update(line.key, { requested_discount_pct: e.target.value.replace(/[^\d.]/g, '') })}
                            className="h-8 w-16 px-2 rounded-lg bg-slate-50 border border-slate-200 text-xs font-bold tabular text-right text-slate-800 outline-none"
                            placeholder="0%"
                          />
                        </td>
                      )}
                      {canDiscount && (
                        <td>
                          <input
                            type="text"
                            value={line.requested_discount_reason}
                            disabled={disabled}
                            onChange={(e) => update(line.key, { requested_discount_reason: e.target.value })}
                            className="h-8 px-2.5 rounded-lg bg-slate-50 border border-slate-200 text-xs text-slate-800 outline-none w-full"
                            placeholder="Reason for discount…"
                          />
                        </td>
                      )}
                      <td className="text-right">
                        <Button size="sm" variant="ghost" disabled={disabled} onClick={() => onChange(lines.filter((l) => l.key !== line.key))} aria-label="Remove line">
                          <Trash2 size={14} className="text-slate-400 hover:text-rose-600" />
                        </Button>
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>

          {/* Mobile Card View (screens < 768px) */}
          <div className="md:hidden space-y-2.5">
            {lines.map((line) => {
              const uom = line.uoms.find((u) => u.uom_id === line.uom_id)
              return (
                <div key={line.key} className="p-3.5 rounded-xl border border-slate-200 bg-white shadow-2xs space-y-2.5">
                  <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0 flex-1">
                      <div className="font-bold text-sm text-slate-900">{line.product.name}</div>
                      <div className="text-xs text-slate-500 font-mono mt-0.5">
                        #{line.product.code} {line.product.strength ? `· ${line.product.strength}` : ''}
                      </div>
                    </div>
                    <button
                      type="button"
                      disabled={disabled}
                      onClick={() => onChange(lines.filter((l) => l.key !== line.key))}
                      className="p-1 rounded-lg text-slate-400 hover:text-rose-600"
                    >
                      <Trash2 size={16} />
                    </button>
                  </div>

                  <div className="flex items-center justify-between gap-3 pt-1 border-t border-slate-100">
                    <div className="flex items-center gap-1.5">
                      <span className="text-xs text-slate-500 font-semibold">UOM:</span>
                      <select
                        value={line.uom_id}
                        disabled={disabled}
                        onChange={(e) => update(line.key, { uom_id: e.target.value, unit_price: '' })}
                        className="h-8 px-2 rounded-lg bg-slate-50 border border-slate-200 text-xs font-bold text-slate-800"
                      >
                        {line.uoms.map((u) => (
                          <option key={u.uom_id} value={u.uom_id}>
                            {u.uom?.code} {u.factor_to_base !== 1 ? `(×${u.factor_to_base})` : ''}
                          </option>
                        ))}
                      </select>
                    </div>

                    <div className="flex items-center gap-1.5">
                      <span className="text-xs text-slate-500 font-semibold">Qty:</span>
                      <QuantityInput
                        value={line.quantity}
                        onChange={(quantity) => update(line.key, { quantity })}
                        uomCode=""
                        factorToBase={uom?.factor_to_base ?? 1}
                        baseUomCode={line.product.base_uom?.code ?? 'unit'}
                        isDiscrete={line.product.is_discrete}
                        compact
                        disabled={disabled}
                      />
                    </div>
                  </div>

                  {pricing && <LinePriceRow line={line} pricing={pricing} disabled={disabled} onPrice={(unit_price) => update(line.key, { unit_price })} />}

                  {canDiscount && (
                    <div className="grid grid-cols-3 gap-2 pt-1 border-t border-slate-100">
                      <input
                        type="text"
                        inputMode="decimal"
                        value={line.requested_discount_pct}
                        disabled={disabled}
                        onChange={(e) => update(line.key, { requested_discount_pct: e.target.value.replace(/[^\d.]/g, '') })}
                        className="h-8 px-2 rounded-lg bg-slate-50 border border-slate-200 text-xs font-bold text-right text-slate-800"
                        placeholder="Disc %"
                      />
                      <input
                        type="text"
                        value={line.requested_discount_reason}
                        disabled={disabled}
                        onChange={(e) => update(line.key, { requested_discount_reason: e.target.value })}
                        className="col-span-2 h-8 px-2 rounded-lg bg-slate-50 border border-slate-200 text-xs text-slate-800"
                        placeholder="Reason for discount…"
                      />
                    </div>
                  )}
                </div>
              )
            })}
          </div>
        </>
      )}
    </div>
  )
}

export function linesPayload(lines: EditableLine[]) {
  return lines.map((l) => ({
    product_id: l.product.id,
    uom_id: l.uom_id,
    quantity: l.quantity,
    requested_discount_pct: l.requested_discount_pct && Number(l.requested_discount_pct) > 0 ? l.requested_discount_pct : null,
    requested_discount_reason: l.requested_discount_reason || null,
    selling_price: isValidDecimal(l.unit_price.trim()) && dIsPos(l.unit_price.trim()) ? l.unit_price.trim() : null,
  }))
}
