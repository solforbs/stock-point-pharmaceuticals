import { Trash2 } from 'lucide-react'
import { ProductSearch } from '../../components/ProductSearch'
import { Button } from '../../components/ui/primitives'
import { formatDate, titleCase } from '../../lib/format'
import { useProductStock } from '../../lib/hooks'
import { formatQty } from '../../lib/money'
import type { Product, ReturnReason } from '../../lib/types'
import { RETURN_REASON_LABEL } from '../sales/returnReasons'

export type BatchLine = { key: string; product: Product; batch_id: string; qty_base: string; return_reason?: ReturnReason | ''; remarks?: string }

function Row({ line, storeId, statuses, reasons, onChange, onRemove }: { line: BatchLine; storeId: string; statuses?: string[]; reasons?: ReturnReason[]; onChange: (patch: Partial<BatchLine>) => void; onRemove: () => void }) {
  const stock = useProductStock(line.product.id)
  const row = stock.data?.stores.find((s) => s.store_id === storeId)
  const batches = (row?.batches ?? []).filter((b) => !statuses || statuses.includes(b.status))
  const chosen = batches.find((b) => b.batch_id === line.batch_id)
  return (
    <tr>
      <td>
        <div className="font-semibold text-slate-900">
          {line.product.name}
          {line.product.strength && !line.product.name.includes(line.product.strength) && <span className="ml-1 text-slate-700">{line.product.strength}</span>}
        </div>
        <div className="text-xs text-slate-500 mt-0.5">
          <span className="font-mono">{line.product.code}</span> · base {line.product.base_uom?.code ?? ''}
          {(line.product.generic_name || line.product.description) && <> · {[line.product.generic_name, line.product.description].filter(Boolean).join(' · ')}</>}
        </div>
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
      {reasons && (
        <td className="min-w-55 space-y-1">
          <select value={line.return_reason ?? ''} onChange={(e) => onChange({ return_reason: e.target.value as ReturnReason | '' })} className="ui-input h-8 text-sm w-full">
            <option value="">Reason…</option>
            {reasons.map((r) => (<option key={r} value={r}>{RETURN_REASON_LABEL[r]}</option>))}
          </select>
          <input
            value={line.remarks ?? ''}
            onChange={(e) => onChange({ remarks: e.target.value })}
            maxLength={500}
            placeholder="Remarks"
            className={`ui-input h-8 text-sm w-full ${line.return_reason === 'OTHER' && !line.remarks?.trim() ? '!border-rose-500' : ''}`}
          />
        </td>
      )}
      <td className="text-right">
        <Button size="sm" variant="ghost" onClick={onRemove} aria-label="Remove"><Trash2 size={14} /></Button>
      </td>
    </tr>
  )
}

/**
 * Product → batch (from the store's balances) → base quantity. Shared by
 * transfers, waste, supplier returns; pass `reasons` to also capture why
 * each line is going back.
 */
export function BatchLinesEditor({ lines, onChange, storeId, statuses, reasons, disabled }: { lines: BatchLine[]; onChange: (lines: BatchLine[]) => void; storeId: string; statuses?: string[]; reasons?: ReturnReason[]; disabled?: boolean }) {
  return (
    <div className="space-y-2">
      <ProductSearch disabled={disabled || !storeId} onSelect={(p) => onChange([...lines, { key: `${p.id}-${Date.now()}`, product: p, batch_id: '', qty_base: '', ...(reasons ? { return_reason: '' as const, remarks: '' } : {}) }])} placeholder={storeId ? 'Add product…' : 'Choose a store first'} />
      {lines.length > 0 && (
        <table className="ui-table">
          <thead><tr><th>Product</th><th>Batch · expiry</th><th>Qty (base)</th>{reasons && <th>Reason &amp; remarks</th>}<th /></tr></thead>
          <tbody>
            {lines.map((l) => (
              <Row key={l.key} line={l} storeId={storeId} statuses={statuses} reasons={reasons} onChange={(patch) => onChange(lines.map((x) => (x.key === l.key ? { ...x, ...patch } : x)))} onRemove={() => onChange(lines.filter((x) => x.key !== l.key))} />
            ))}
          </tbody>
        </table>
      )}
    </div>
  )
}

export function batchLinesValid(lines: BatchLine[]): boolean {
  return lines.length > 0 && lines.every((l) => l.batch_id && /^\d+(\.\d+)?$/.test(l.qty_base) && Number(l.qty_base) > 0 && (l.return_reason !== 'OTHER' || !!l.remarks?.trim()))
}

export function batchLinesPayload(lines: BatchLine[]) {
  return lines.map((l) => ({
    product_id: l.product.id,
    batch_id: l.batch_id,
    qty_base: l.qty_base,
    ...(l.return_reason !== undefined ? { return_reason: l.return_reason || null, remarks: l.remarks?.trim() || null } : {}),
  }))
}
