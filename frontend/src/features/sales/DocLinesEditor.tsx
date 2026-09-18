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
      {lines.length > 0 && (
        <table className="ui-table">
          <thead>
            <tr>
              <th>Product</th>
              <th>UOM</th>
              <th>Qty</th>
              {canDiscount && <th>Discount %</th>}
              {canDiscount && <th>Reason</th>}
              <th />
            </tr>
          </thead>
          <tbody>
            {lines.map((line) => {
              const uom = line.uoms.find((u) => u.uom_id === line.uom_id)
              return (
                <tr key={line.key}>
                  <td>
                    <div className="font-semibold">{line.product.name}</div>
                    <div className="text-[10.5px] text-[var(--text-muted)]">{line.product.code}</div>
                  </td>
                  <td>
                    <select value={line.uom_id} disabled={disabled} onChange={(e) => update(line.key, { uom_id: e.target.value })} className="ui-input h-7 w-auto">
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
                        className="ui-input h-7 w-16 tabular text-right"
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
                        className="ui-input h-7"
                        placeholder="Required with a discount"
                      />
                    </td>
                  )}
                  <td className="text-right">
                    <Button size="sm" variant="ghost" disabled={disabled} onClick={() => onChange(lines.filter((l) => l.key !== line.key))} aria-label="Remove line">
                      <Trash2 size={13} />
                    </Button>
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
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
