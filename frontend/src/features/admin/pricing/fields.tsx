import { X } from 'lucide-react'
import { ProductSearch } from '../../../components/ProductSearch'
import { Select } from '../../../components/ui/primitives'
import { useProduct } from '../../../lib/hooks'
import type { Product } from '../../../lib/types'

/** A chosen product (with a clear button), or the search box when none is chosen. Loads the product's units. */
export function ProductField({ productId, fallbackName, onChange, disabled }: { productId: string; fallbackName?: string; onChange: (product: Product | null) => void; disabled?: boolean }) {
  const product = useProduct(productId || null)
  if (!productId) return <ProductSearch onSelect={onChange} placeholder="Search product…" disabled={disabled} />
  return (
    <div className="ui-input flex items-center gap-2">
      <span className="flex-1 truncate">{product.data ? `${product.data.code} · ${product.data.name}` : fallbackName ?? 'Loading…'}</span>
      {!disabled && (
        <button type="button" aria-label="Clear product" onClick={() => onChange(null)} className="text-slate-400 hover:text-slate-600"><X size={12} /></button>
      )}
    </div>
  )
}

/** Sales units of a product, as the engine prices per product-and-unit. */
export function UomSelect({ productId, value, onChange, disabled }: { productId: string; value: string; onChange: (uomId: string) => void; disabled?: boolean }) {
  const product = useProduct(productId || null)
  const uoms = (product.data?.uoms ?? []).filter((u) => u.is_sales)
  return (
    <Select value={value} onChange={(e) => onChange(e.target.value)} disabled={disabled || !productId}>
      <option value="">{productId ? (product.isLoading ? 'Loading…' : 'Unit…') : 'Pick a product'}</option>
      {uoms.map((u) => (
        <option key={u.uom_id} value={u.uom_id}>{u.uom?.code ?? u.uom_id} ×{u.factor_to_base}</option>
      ))}
    </Select>
  )
}
