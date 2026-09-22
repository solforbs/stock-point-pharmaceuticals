import { Pill, Trash2, Truck } from 'lucide-react'
import { ProductSearch } from '../../components/ProductSearch'
import { Drawer } from '../../components/ui/Drawer'
import { InlineError } from '../../components/ui/States'
import { Button, DrawerFooter, Field, FormSection, Input, Select } from '../../components/ui/primitives'
import type { Product, Supplier } from '../../lib/types'
import { decimalInput, netFromTrade } from './tradeTerms'

export interface PoLine {
  key: string
  product: Product
  uom_id: string
  qty_ordered: string
  unit_price: string
  /** The supplier's gross price and discount; together they fill the unit price. */
  trade_price: string
  discount_pct: string
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
    lines.every((l) => l.uom_id && Number(l.qty_ordered) > 0 && /^\d+(\.\d+)?$/.test(l.unit_price) && Number(l.discount_pct || 0) <= 100)

  return (
    <Drawer
      open={open}
      onClose={onClose}
      title="Create Purchase Order"
      subtitle="Issue formal supplier order with locked purchase prices and UOM conversion"
      width={840}
    >
      <div className="space-y-4">
        {/* Section 1: Supplier & Delivery */}
        <FormSection
          title="Supplier & Delivery Schedule"
          description="Choose registered supplier and estimated warehouse delivery date"
          icon={Truck}
        >
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
        </FormSection>

        {/* Section 2: Order Lines */}
        <FormSection
          title="Medicine Order Lines"
          description="Search products and specify purchase units of measure and unit cost"
          icon={Pill}
          badge={`${lines.length} items`}
        >
          <div className="space-y-3">
            <ProductSearch
              onSelect={(p) => {
                const uoms = (p.uoms ?? []).filter((u) => u.is_purchase || u.is_base)
                const uom = uoms.find((u) => u.is_purchase && !u.is_base) ?? uoms[0]
                setLines([
                  ...lines,
                  { key: `${p.id}-${Date.now()}`, product: p, uom_id: uom?.uom_id ?? '', qty_ordered: '1', unit_price: '', trade_price: '', discount_pct: '' },
                ])
              }}
            />

            {lines.length > 0 ? (
              <div className="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-2xs">
                <table className="ui-table min-w-[720px]">
                  <thead>
                    <tr>
                      <th>Product</th>
                      <th>UOM</th>
                      <th className="text-right">Qty</th>
                      <th className="text-right">Trade price</th>
                      <th className="text-right">Discount %</th>
                      <th className="text-right">Unit Price (KES)</th>
                      <th />
                    </tr>
                  </thead>
                  <tbody>
                    {lines.map((l) => (
                      <tr key={l.key}>
                        <td>
                          <div className="font-bold text-slate-900">{l.product.name}</div>
                          <div className="text-xs text-slate-400 font-mono">{l.product.code}</div>
                        </td>
                        <td>
                          <select
                            value={l.uom_id}
                            onChange={(e) =>
                              setLines(lines.map((x) => (x.key === l.key ? { ...x, uom_id: e.target.value } : x)))
                            }
                            className="ui-input h-8 w-auto text-sm font-semibold"
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
                            className="ui-input h-8 w-20 tabular text-right font-bold text-sm"
                          />
                        </td>
                        <td>
                          <input
                            type="text"
                            inputMode="decimal"
                            placeholder="Optional"
                            value={l.trade_price}
                            onChange={(e) => {
                              const trade_price = decimalInput(e.target.value)
                              setLines(lines.map((x) => (x.key === l.key ? { ...x, trade_price, unit_price: netFromTrade(trade_price, x.discount_pct) || x.unit_price } : x)))
                            }}
                            className="ui-input h-8 w-24 tabular text-right text-sm"
                          />
                        </td>
                        <td>
                          <input
                            type="text"
                            inputMode="decimal"
                            placeholder="0"
                            value={l.discount_pct}
                            onChange={(e) => {
                              const discount_pct = decimalInput(e.target.value)
                              setLines(lines.map((x) => (x.key === l.key ? { ...x, discount_pct, unit_price: netFromTrade(x.trade_price, discount_pct) || x.unit_price } : x)))
                            }}
                            className="ui-input h-8 w-16 tabular text-right text-sm"
                          />
                        </td>
                        <td>
                          <input
                            type="text"
                            inputMode="decimal"
                            placeholder="0.00"
                            title={l.trade_price ? 'Filled from trade price less discount. Change it to match the invoice.' : undefined}
                            value={l.unit_price}
                            onChange={(e) =>
                              setLines(lines.map((x) => (x.key === l.key ? { ...x, unit_price: e.target.value.replace(/[^\d.]/g, '') } : x)))
                            }
                            className="ui-input h-8 w-28 tabular text-right font-bold text-sm"
                          />
                        </td>
                        <td className="text-right">
                          <Button
                            size="xs"
                            variant="ghost"
                            onClick={() => setLines(lines.filter((x) => x.key !== l.key))}
                            aria-label="Remove line"
                            className="text-slate-400 hover:text-rose-600 hover:bg-rose-50"
                          >
                            <Trash2 size={14} />
                          </Button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : (
              <div className="p-6 text-center border border-dashed border-slate-200 rounded-xl bg-slate-50/60 text-xs text-slate-500">
                Use the search box above to add medicine lines to this purchase order.
              </div>
            )}
          </div>
        </FormSection>

        {error ? <InlineError error={error} /> : null}

        {/* Sticky Glassmorphic Footer */}
        <DrawerFooter
          onCancel={onClose}
          onSubmit={onSubmit}
          submitLabel="Create Purchase Order"
          isSubmitting={isSubmitting}
          disabled={!supplierId || !validLines}
        />
      </div>
    </Drawer>
  )
}
