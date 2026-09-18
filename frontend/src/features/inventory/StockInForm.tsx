import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { InlineError } from '../../components/ui/States'
import { Button, Field, Input, Select } from '../../components/ui/primitives'
import { apiPost, getApiError } from '../../lib/api'
import { addDaysIso } from '../../lib/format'
import { useStores, useSuppliers } from '../../lib/hooks'
import { formatKes } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Product } from '../../lib/types'

type ReceiptResponse = { id: string; doc_number: string; lines: { batch_id: string | null; batch?: { id: string; batch_number: string } | null }[] }

/**
 * Stock in for one product without a purchase order: an emergency goods
 * receipt (Part 9.2) posted through the normal GRN path — ledger row, landed
 * cost, Dr Inventory / Cr GRN accrual. The batch arrives PENDING_QC; a holder
 * of quality.release can release it for sale in the same step.
 */
export function StockInForm({ product, onDone, onCancel }: { product: Product; onDone: () => void; onCancel: () => void }) {
  const queryClient = useQueryClient()
  const stores = useStores()
  const suppliers = useSuppliers()
  const canRelease = usePermission('quality.release')
  const purchaseUoms = (product.uoms ?? []).filter((u) => u.is_purchase || u.is_base)
  const defaultUom = purchaseUoms.find((u) => !u.is_base) ?? purchaseUoms[0]

  const [storeId, setStoreId] = useState('')
  const [supplierId, setSupplierId] = useState('')
  const [uomId, setUomId] = useState(defaultUom?.uom_id ?? product.base_uom_id)
  const [qty, setQty] = useState('')
  const [batch, setBatch] = useState('')
  const [expiry, setExpiry] = useState(addDaysIso(365))
  const [unitCost, setUnitCost] = useState('')
  const [release, setRelease] = useState(canRelease)

  const effectiveStore = storeId || stores.data?.find((s) => s.code === 'MAIN')?.id || stores.data?.[0]?.id || ''
  const effectiveSupplier = supplierId || suppliers.data?.data?.[0]?.id || ''
  const factor = (product.uoms ?? []).find((u) => u.uom_id === uomId)?.factor_to_base ?? 1
  const uomCode = (product.uoms ?? []).find((u) => u.uom_id === uomId)?.uom?.code ?? ''

  const receive = useMutation({
    meta: { silent: true },
    mutationFn: async () => {
      const receipt = await apiPost<ReceiptResponse>('/api/goods-receipts', {
        supplier_id: effectiveSupplier,
        store_id: effectiveStore,
        is_emergency: true,
        lines: [{ product_id: product.id, uom_id: uomId, qty_delivered: qty, qty_accepted: qty, batch_number: batch.trim(), expiry_date: expiry, unit_cost: unitCost }],
      })
      const batchId = receipt.lines[0]?.batch_id ?? receipt.lines[0]?.batch?.id
      let released = false
      if (release && batchId) {
        await apiPost(`/api/batches/${batchId}/release`, { justification: `Released on receipt ${receipt.doc_number}` })
        released = true
      }
      return { receipt, released }
    },
    onSuccess: ({ receipt, released }) => {
      toast.success(`Received ${qty} ${uomCode} of ${product.code}`, `${receipt.doc_number} · ${released ? 'released for sale' : 'awaiting QC release in Quarantine'}`)
      for (const key of ['products', 'inventory', 'batches', 'goods-receipts', 'dashboard']) queryClient.invalidateQueries({ queryKey: [key] })
      onDone()
    },
  })
  const err = receive.isError ? getApiError(receive.error) : null
  const lineErr = (field: string) => err?.errors[`lines.0.${field}`]?.[0]
  const value = (Number(qty) || 0) * (Number(unitCost) || 0)

  return (
    <div className="space-y-4">
      <div className="text-[12px] text-[var(--text-secondary)]">
        Receives stock without a purchase order (an emergency goods receipt). For supplier deliveries against an order use <Link to="/buy/goods-receipts" className="underline">Goods Receipts</Link>; for the go-live stock take use <Link to="/inventory/opening-stock" className="underline">Opening Stock</Link>.
      </div>
      <div className="grid grid-cols-2 gap-3">
        <Field label="Store" required>
          <Select value={effectiveStore} onChange={(e) => setStoreId(e.target.value)}>
            {(stores.data ?? []).map((s) => (<option key={s.id} value={s.id}>{s.code} · {s.name}</option>))}
          </Select>
        </Field>
        <Field label="Supplier" required error={err?.errors.supplier_id?.[0]}>
          <Select value={effectiveSupplier} onChange={(e) => setSupplierId(e.target.value)}>
            {(suppliers.data?.data ?? []).length === 0 && <option value="">No suppliers — add one under Buy → Suppliers</option>}
            {(suppliers.data?.data ?? []).map((s) => (<option key={s.id} value={s.id}>{s.code} · {s.name}</option>))}
          </Select>
        </Field>
        <Field label="Unit" required>
          <Select value={uomId} onChange={(e) => setUomId(e.target.value)}>
            {purchaseUoms.map((u) => (<option key={u.uom_id} value={u.uom_id}>{u.uom?.code} {u.factor_to_base > 1 ? `(× ${u.factor_to_base})` : ''}</option>))}
          </Select>
        </Field>
        <Field label={`Quantity (${uomCode})`} required error={lineErr('qty_accepted')} hint={factor > 1 && qty ? `= ${Number(qty) * factor} base units` : undefined}>
          <Input inputMode="decimal" className="tabular" value={qty} onChange={(e) => setQty(e.target.value.replace(/[^\d.]/g, ''))} />
        </Field>
        <Field label="Batch number" required error={lineErr('batch_number')}>
          <Input value={batch} onChange={(e) => setBatch(e.target.value.toUpperCase())} />
        </Field>
        <Field label="Expiry date" required error={lineErr('expiry_date') ?? (err?.code === 'BATCH_EXPIRED' ? err.message : undefined)}>
          <Input type="date" value={expiry} onChange={(e) => setExpiry(e.target.value)} />
        </Field>
        <Field label={`Unit cost per ${uomCode} (KES)`} required error={lineErr('unit_cost')} hint={value > 0 ? `Stock value ${formatKes(value.toFixed(2))}` : undefined}>
          <Input inputMode="decimal" className="tabular" value={unitCost} onChange={(e) => setUnitCost(e.target.value.replace(/[^\d.]/g, ''))} />
        </Field>
        {canRelease && (
          <label className="flex items-center gap-2 text-[12px] pt-5">
            <input type="checkbox" checked={release} onChange={(e) => setRelease(e.target.checked)} /> Release for sale now (QC checked)
          </label>
        )}
      </div>
      {err && Object.keys(err.errors).length === 0 && err.code !== 'BATCH_EXPIRED' && <InlineError error={receive.error} />}
      <div className="flex justify-end gap-2">
        <Button onClick={onCancel}>Cancel</Button>
        <Button variant="primary" disabled={!effectiveStore || !effectiveSupplier || !qty || !batch.trim() || !expiry || unitCost === '' || receive.isPending} onClick={() => receive.mutate()}>
          {receive.isPending ? 'Receiving…' : 'Receive stock'}
        </Button>
      </div>
    </div>
  )
}
