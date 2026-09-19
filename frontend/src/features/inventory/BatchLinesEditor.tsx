import { Trash2 } from 'lucide-react'
import { ProductSearch } from '../../components/ProductSearch'
import { Button } from '../../components/ui/primitives'
import { formatDate, titleCase } from '../../lib/format'
import { useProductStock } from '../../lib/hooks'
import { formatQty } from '../../lib/money'
import type { Product } from '../../lib/types'

export type BatchLine = { key: string; product: Product; batch_id: string; qty_base: string }

function Row({ line, storeId, statuses, onChange, onRemove }: { line: BatchLine; storeId: string; statuses?: string[]; onChange: (patch: Partial<BatchLine>) => void; onRemove: () => void }) {
  const stock = useProductStock(line.product.id)
  const row = stock.data?.stores.find((s) => s.store_id === storeId)
  const batches = (row?.batches ?? []).filter((b) => !statuses || statuses.includes(b.status))
  const chosen = batches.find((b) => b.batch_id === line.batch_id)
  return (
    <tr>
      <td>
        <div className="font-semibold text-slate-900">{line.product.name}</div>
        <div className="text-xs text-slate-500 font-mono mt-0.5">{line.product.code} · base {line.product.base_uom?.code ?? ''}</div>
      </td>
      <td>
        <select value={line.batch_id} onChange={(e) => onChange({ batch_id: e.target.value })} className="ui-input h-8 text-sm">
          <option value="">Batch…</option>
          {batches.map((b) => (
            <option key={b.batch_id} value={b.batch_id}>
              {b.batch_number} · exp {formatDate(b.expiry_date)} · {formatQty(b.on_hand)} on hand · {titleCase(b.status)}
            </option>
          ))}
        </select>
        {!stock.isLoading && batches.length === 0 && <div className="text-xs text-rose-600 font-medium mt-0.5">No eligible batches in this store.</div>}
      </td>
      <td>
        <input type="text" inputMode="decimal" value={line.qty_base} onChange={(e) => onChange({ qty_base: e.target.value.replace(/[^\d.]/g, '') })} className="ui-input h-8 w-24 tabular text-right text-sm" />
        {chosen && Number(line.qty_base) > Number(chosen.on_hand) && <div className="text-xs text-amber-700 font-medium mt-0.5">Above on-hand</div>}
      </td>
      <td className="text-right">
        <Button size="sm" variant="ghost" onClick={onRemove} aria-label="Remove"><Trash2 size={14} /></Button>
      </td>
    </tr>
  )
}

/** Product → batch (from the store's balances) → base quantity. Shared by transfers, waste, supplier returns. */
export function BatchLinesEditor({ lines, onChange, storeId, statuses, disabled }: { lines: BatchLine[]; onChange: (lines: BatchLine[]) => void; storeId: string; statuses?: string[]; disabled?: boolean }) {
  return (
    <div className="space-y-2">
      <ProductSearch disabled={disabled || !storeId} onSelect={(p) => onChange([...lines, { key: `${p.id}-${Date.now()}`, product: p, batch_id: '', qty_base: '' }])} placeholder={storeId ? 'Add product…' : 'Choose a store first'} />
      {lines.length > 0 && (
        <table className="ui-table">
          <thead><tr><th>Product</th><th>Batch</th><th>Qty (base)</th><th /></tr></thead>
          <tbody>
            {lines.map((l) => (
              <Row key={l.key} line={l} storeId={storeId} statuses={statuses} onChange={(patch) => onChange(lines.map((x) => (x.key === l.key ? { ...x, ...patch } : x)))} onRemove={() => onChange(lines.filter((x) => x.key !== l.key))} />
            ))}
          </tbody>
        </table>
      )}
    </div>
  )
}

export function batchLinesValid(lines: BatchLine[]): boolean {
  return lines.length > 0 && lines.every((l) => l.batch_id && /^\d+(\.\d+)?$/.test(l.qty_base) && Number(l.qty_base) > 0)
}

export function batchLinesPayload(lines: BatchLine[]) {
  return lines.map((l) => ({ product_id: l.product.id, batch_id: l.batch_id, qty_base: l.qty_base }))
}
