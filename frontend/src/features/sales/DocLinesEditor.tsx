import { Trash2 } from 'lucide-react'
import { defaultSalesUom, ProductSearch } from '../../components/ProductSearch'
import { QuantityInput } from '../../components/ui/QuantityInput'
import { Button } from '../../components/ui/primitives'
import type { Product, ProductUom } from '../../lib/types'

export type EditableLine = {
  key: string
  product: Product
  uoms: ProductUom[]
  uom_id: string
  quantity: string
  requested_discount_pct: string
  requested_discount_reason: string
}

let counter = 0

export function lineFromProduct(product: Product, purchase = false): EditableLine | null {
  const uoms = (product.uoms ?? []).filter((u) => (purchase ? u.is_purchase || u.is_base : u.is_sales || u.is_base))
  const chosen = purchase ? (uoms.find((u) => u.is_purchase) ?? uoms[0]) : defaultSalesUom(product)
  if (!chosen) return null
  counter += 1
  return { key: `${product.id}-${counter}`, product, uoms, uom_id: chosen.uom_id, quantity: '1', requested_discount_pct: '', requested_discount_reason: '' }
}

/** Shared line editor for quotations and sales orders: product, UOM, qty, discount. */
export function DocLinesEditor({
  lines,
  onChange,
  canDiscount,
  disabled,
}: {
  lines: EditableLine[]
  onChange: (lines: EditableLine[]) => void
  canDiscount: boolean
  disabled?: boolean
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
          <p className="text-[11.5px] text-slate-400 mt-0.5">Use the search bar above to select medicines by name, SKU, or barcode.</p>
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
                        <div className="font-extrabold text-[13.5px] text-slate-900">{line.product.name}</div>
                        <div className="text-[11.5px] text-slate-500 font-mono mt-0.5">
                          #{line.product.code} {line.product.strength ? `· ${line.product.strength}` : ''}
                        </div>
                      </td>
                      <td>
                        <select
                          value={line.uom_id}
                          disabled={disabled}
                          onChange={(e) => update(line.key, { uom_id: e.target.value })}
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
                      <div className="font-extrabold text-[13.5px] text-slate-900">{line.product.name}</div>
                      <div className="text-[11.5px] text-slate-500 font-mono mt-0.5">
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
                      <span className="text-[11.5px] text-slate-500 font-bold">UOM:</span>
                      <select
                        value={line.uom_id}
                        disabled={disabled}
                        onChange={(e) => update(line.key, { uom_id: e.target.value })}
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
                      <span className="text-[11.5px] text-slate-500 font-bold">Qty:</span>
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
  }))
}
