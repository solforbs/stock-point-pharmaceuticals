import { Trash2 } from 'lucide-react'
import { ProductSearch } from '../../components/ProductSearch'
import { Drawer } from '../../components/ui/Drawer'
import { InlineError } from '../../components/ui/States'
import { Button, Field, Input, Select } from '../../components/ui/primitives'
import type { Product, Supplier } from '../../lib/types'

export interface PoLine {
  key: string
  product: Product
  uom_id: string
  qty_ordered: string
  unit_price: string
}

export interface NewPurchaseOrderDrawerProps {
  open: boolean
  onClose: () => void
  suppliers: Supplier[]
  supplierId: string
  setSupplierId: (id: string) => void
  expectedDate: string
  setExpectedDate: (date: string) => void
  lines: PoLine[]
  setLines: (lines: PoLine[]) => void
  onSubmit: () => void
  isSubmitting: boolean
  error?: unknown
}

export function NewPurchaseOrderDrawer({
  open,
  onClose,
  suppliers,
  supplierId,
  setSupplierId,
  expectedDate,
  setExpectedDate,
  lines,
  setLines,
  onSubmit,
  isSubmitting,
  error,
}: NewPurchaseOrderDrawerProps) {
  const validLines =
    lines.length > 0 &&
    lines.every((l) => l.uom_id && Number(l.qty_ordered) > 0 && /^\d+(\.\d+)?$/.test(l.unit_price))

  return (
    <Drawer open={open} onClose={onClose} title="New Purchase Order" width={820}>
      <div className="space-y-4">
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <Field label="Supplier" required>
            <Select value={supplierId} onChange={(e) => setSupplierId(e.target.value)}>
              <option value="">Choose supplier…</option>
              {suppliers.map((s) => (
                <option key={s.id} value={s.id} disabled={s.status !== 'ACTIVE' || !s.is_active}>
                  {s.name}
                  {s.status !== 'ACTIVE' ? ` (${s.status})` : ''}
                </option>
              ))}
            </Select>
          </Field>
          <Field label="Expected Delivery Date">
            <Input type="date" value={expectedDate} onChange={(e) => setExpectedDate(e.target.value)} />
          </Field>
        </div>

        <Field label="Order Lines" required hint="Unit price is per selected purchase UOM.">
          <div className="space-y-2">
            <ProductSearch
              onSelect={(p) => {
                const uoms = (p.uoms ?? []).filter((u) => u.is_purchase || u.is_base)
                const uom = uoms.find((u) => u.is_purchase && !u.is_base) ?? uoms[0]
                setLines([
                  ...lines,
                  { key: `${p.id}-${Date.now()}`, product: p, uom_id: uom?.uom_id ?? '', qty_ordered: '1', unit_price: '' },
                ])
              }}
            />
            {lines.length > 0 && (
              <div className="overflow-x-auto rounded-lg border border-[var(--border)] bg-white">
                <table className="ui-table min-w-[520px]">
                  <thead>
                    <tr>
                      <th>Product</th>
                      <th>UOM</th>
                      <th className="text-right">Qty</th>
                      <th className="text-right">Unit price</th>
                      <th />
                    </tr>
                  </thead>
                  <tbody>
                    {lines.map((l) => (
                      <tr key={l.key}>
                        <td>
                          <div className="font-semibold text-[var(--text)]">{l.product.name}</div>
                          <div className="text-[10.5px] text-[var(--text-muted)]">{l.product.code}</div>
                        </td>
                        <td>
                          <select
                            value={l.uom_id}
                            onChange={(e) =>
                              setLines(lines.map((x) => (x.key === l.key ? { ...x, uom_id: e.target.value } : x)))
                            }
                            className="ui-input h-7 w-auto"
                          >
                            {(l.product.uoms ?? [])
                              .filter((u) => u.is_purchase || u.is_base)
                              .map((u) => (
                                <option key={u.uom_id} value={u.uom_id}>
                                  {u.uom?.code} {u.factor_to_base !== 1 ? `(×${u.factor_to_base})` : ''}
                                </option>
                              ))}
                          </select>
                        </td>
                        <td>
                          <input
                            type="text"
                            inputMode="decimal"
                            value={l.qty_ordered}
                            onChange={(e) =>
                              setLines(lines.map((x) => (x.key === l.key ? { ...x, qty_ordered: e.target.value.replace(/[^\d.]/g, '') } : x)))
                            }
                            className="ui-input h-7 w-20 tabular text-right"
                          />
                        </td>
                        <td>
                          <input
                            type="text"
                            inputMode="decimal"
                            value={l.unit_price}
                            onChange={(e) =>
                              setLines(lines.map((x) => (x.key === l.key ? { ...x, unit_price: e.target.value.replace(/[^\d.]/g, '') } : x)))
                            }
                            className="ui-input h-7 w-28 tabular text-right"
                          />
                        </td>
                        <td className="text-right">
                          <Button
                            size="sm"
                            variant="ghost"
                            onClick={() => setLines(lines.filter((x) => x.key !== l.key))}
                            aria-label="Remove"
                          >
                            <Trash2 size={13} />
                          </Button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </Field>

        {error ? <InlineError error={error} /> : null}

        <div className="flex flex-col-reverse sm:flex-row justify-end gap-2 pt-3 border-t border-[var(--border)] mt-4">
          <Button onClick={onClose} className="w-full sm:w-auto">Cancel</Button>
          <Button
            variant="primary"
            disabled={!supplierId || !validLines || isSubmitting}
            onClick={onSubmit}
            className="w-full sm:w-auto"
          >
            {isSubmitting ? 'Saving…' : 'Create Purchase Order'}
          </Button>
        </div>
      </div>
    </Drawer>
  )
}
