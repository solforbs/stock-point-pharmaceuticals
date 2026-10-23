import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Modal } from '../../components/ui/Modal'
import { InlineError } from '../../components/ui/States'
import { Button, Field, Input, Select, Textarea } from '../../components/ui/primitives'
import { apiPost, getApiError } from '../../lib/api'
import { useSuppliers } from '../../lib/hooks'
import { formatQty } from '../../lib/money'
import { toast } from '../../lib/toast'
import type { PurchaseOrder, Requisition } from '../../lib/types'
import { SINGLE_SOURCE_REASONS } from './sourcing'

type Line = { requisition_line_id: string; product: string; requested: string; baseUnit: string; uoms: { uom_id: string; code: string; factor: number }[]; uom_id: string; qty_ordered: string; unit_price: string }

function linesFor(requisition: Requisition): Line[] {
  return (requisition.lines ?? []).map((l) => {
    const uoms = (l.product?.uoms ?? [])
      .filter((u) => u.is_purchase || u.is_base)
      .map((u) => ({ uom_id: u.uom_id, code: u.uom?.code ?? '', factor: Number(u.factor_to_base) || 1 }))
    const preferred = uoms.find((u) => u.factor > 1) ?? uoms[0]
    const qty = preferred ? Math.ceil(Number(l.qty_requested) / preferred.factor) : Number(l.qty_requested)

    return {
      requisition_line_id: l.id,
      product: l.product?.name ?? l.product_id.slice(0, 8),
      requested: String(l.qty_requested),
      baseUnit: l.product?.base_uom?.code ?? '',
      uoms,
      uom_id: preferred?.uom_id ?? '',
      qty_ordered: String(qty),
      unit_price: '',
    }
  })
}

/**
 * Single sourcing: an approved requisition becomes a purchase order to one
 * supplier with no bid analysis, for a recorded reason (direct delivery,
 * the only supplier holding the item, …). The order still goes for approval.
 */
export function SingleSourceModal({ requisition, open, onClose }: { requisition: Requisition; open: boolean; onClose: () => void }) {
  const queryClient = useQueryClient()
  const navigate = useNavigate()
  const suppliers = useSuppliers()
  const [supplierId, setSupplierId] = useState('')
  const [reason, setReason] = useState('')
  const [justification, setJustification] = useState('')
  const [expectedDate, setExpectedDate] = useState('')
  const [lines, setLines] = useState<Line[]>(() => linesFor(requisition))
  const setLine = (id: string, patch: Partial<Line>) => setLines(lines.map((l) => (l.requisition_line_id === id ? { ...l, ...patch } : l)))

  const raise = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<PurchaseOrder>(`/api/requisitions/${requisition.id}/convert`, {
      supplier_id: supplierId,
      single_source_reason: reason,
      justification: justification.trim(),
      expected_date: expectedDate || null,
      lines: lines.map((l) => ({ requisition_line_id: l.requisition_line_id, uom_id: l.uom_id, qty_ordered: l.qty_ordered, unit_price: l.unit_price === '' ? null : l.unit_price })),
    }),
    onSuccess: (po) => {
      onClose()
      toast.success(`Purchase order ${po.doc_number} raised`, 'Single sourced: it now goes for approval like any other order.')
      queryClient.invalidateQueries({ queryKey: ['requisitions'] })
      queryClient.invalidateQueries({ queryKey: ['purchase-orders'] })
      navigate(`/buy/purchase-orders?po=${po.id}`)
    },
  })
  const err = raise.isError ? getApiError(raise.error) : null
  const valid = !!supplierId && !!reason && justification.trim().length >= 10 && lines.length > 0
    && lines.every((l) => l.uom_id && Number(l.qty_ordered) > 0 && (l.unit_price === '' || Number(l.unit_price) >= 0))

  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Single source"
      width={760}
      footer={<><Button onClick={onClose}>Cancel</Button><Button variant="primary" disabled={!valid || raise.isPending} onClick={() => raise.mutate()}>{raise.isPending ? 'Raising…' : 'Raise purchase order'}</Button></>}
    >
      <div className="space-y-3">
        <p className="text-sm text-slate-600 leading-relaxed">
          Order from one supplier without a bid analysis. Record why: the reason and justification stay on the purchase order for its approver and for audit.
        </p>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <Field label="Supplier" required error={err?.errors.supplier_id?.[0]}>
            <Select value={supplierId} onChange={(e) => setSupplierId(e.target.value)}>
              <option value="">Choose supplier…</option>
              {(suppliers.data?.data ?? []).filter((s) => s.status === 'ACTIVE').map((s) => (<option key={s.id} value={s.id}>{s.name}</option>))}
            </Select>
          </Field>
          <Field label="Reason for single sourcing" required error={err?.errors.single_source_reason?.[0]}>
            <Select value={reason} onChange={(e) => setReason(e.target.value)}>
              <option value="">Choose reason…</option>
              {SINGLE_SOURCE_REASONS.map((r) => (<option key={r.value} value={r.value}>{r.label}</option>))}
            </Select>
          </Field>
        </div>
        <Field label="Justification" required hint="At least 10 characters, e.g. who delivers, why no other supplier can." error={err?.errors.justification?.[0]}>
          <Textarea rows={2} value={justification} onChange={(e) => setJustification(e.target.value)} maxLength={1000} />
        </Field>
        <Field label="Expected delivery"><Input type="date" value={expectedDate} onChange={(e) => setExpectedDate(e.target.value)} /></Field>
        <table className="ui-table">
          <thead><tr><th>Product</th><th className="text-right">Requested</th><th>Unit</th><th className="text-right">Qty</th><th className="text-right">Unit price</th></tr></thead>
          <tbody>
            {lines.map((l) => (
              <tr key={l.requisition_line_id}>
                <td className="font-semibold text-slate-900">{l.product}</td>
                <td className="text-right tabular text-slate-500">{formatQty(l.requested)} {l.baseUnit}</td>
                <td>
                  <Select value={l.uom_id} onChange={(e) => setLine(l.requisition_line_id, { uom_id: e.target.value })} aria-label="Unit">
                    {l.uoms.map((u) => (<option key={u.uom_id} value={u.uom_id}>{u.code}{u.factor !== 1 ? ` (×${u.factor})` : ''}</option>))}
                  </Select>
                </td>
                <td className="text-right"><Input className="w-20 text-right" inputMode="decimal" value={l.qty_ordered} onChange={(e) => setLine(l.requisition_line_id, { qty_ordered: e.target.value })} aria-label="Quantity" /></td>
                <td className="text-right"><Input className="w-24 text-right" inputMode="decimal" placeholder="Later" value={l.unit_price} onChange={(e) => setLine(l.requisition_line_id, { unit_price: e.target.value })} aria-label="Unit price" /></td>
              </tr>
            ))}
          </tbody>
        </table>
        {err && !Object.keys(err.errors).length && <InlineError error={raise.error} />}
      </div>
    </Modal>
  )
}
