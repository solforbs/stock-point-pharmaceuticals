import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useEffect, useState } from 'react'
import { Modal } from '../../components/ui/Modal'
import { InlineError } from '../../components/ui/States'
import { Button, Field, Input, Select, Textarea } from '../../components/ui/primitives'
import { apiPost } from '../../lib/api'
import { toast } from '../../lib/toast'
import type { GoodsReceipt, Sale } from '../../lib/types'

type GrnDraft = { id: string; label: string; qty_accepted: string; uom_id: string; unit_cost: string; batch_number: string; expiry_date: string; manufacture_date: string; uoms: { id: string; code: string }[] }

const num = (v: string | number | null | undefined) => (v === null || v === undefined || v === '' ? '' : String(Number(v)))
const day = (v: string | null | undefined) => (v ? v.slice(0, 10) : '')

/**
 * Corrects a posted goods receipt. The server posts reversing and adjusting
 * stock and journal entries; nothing already posted is rewritten.
 */
export function CorrectReceiptModal({ receipt, open, onClose }: { receipt: GoodsReceipt; open: boolean; onClose: () => void }) {
  const queryClient = useQueryClient()
  const [lines, setLines] = useState<GrnDraft[]>([])
  const [receivedAt, setReceivedAt] = useState('')
  const [reason, setReason] = useState('')

  useEffect(() => {
    if (!open) return
    setReason('')
    setReceivedAt(day(receipt.received_at))
    setLines(receipt.lines.map((l) => {
      const productUoms = (l.product as { uoms?: { uom_id: string; uom?: { code: string } | null }[] } | null | undefined)?.uoms ?? []
      const uoms = productUoms.length ? productUoms.map((u) => ({ id: u.uom_id, code: u.uom?.code ?? '' })) : [{ id: l.uom_id, code: l.uom?.code ?? '' }]
      return {
        id: l.id, label: l.product?.name ?? l.product_id, qty_accepted: num(l.qty_accepted), uom_id: l.uom_id, unit_cost: num(l.unit_cost),
        batch_number: l.batch_number, expiry_date: day(l.expiry_date), manufacture_date: day(l.manufacture_date), uoms,
      }
    }))
  }, [open, receipt])

  const save = useMutation({
    mutationFn: () => apiPost<GoodsReceipt>(`/api/goods-receipts/${receipt.id}/correct`, {
      reason,
      received_at: receivedAt || null,
      lines: lines.map(({ label: _label, uoms: _uoms, manufacture_date, ...l }) => ({ ...l, manufacture_date: manufacture_date || null })),
    }),
    onSuccess: (g) => {
      toast.success(`Corrected ${g.doc_number}`, 'Stock, cost and the supplier accrual were adjusted; the original entries stay on record.')
      queryClient.invalidateQueries({ queryKey: ['goods-receipts'] })
      queryClient.invalidateQueries({ queryKey: ['inventory'] })
      onClose()
    },
  })

  const update = (id: string, patch: Partial<GrnDraft>) => setLines(lines.map((l) => (l.id === id ? { ...l, ...patch } : l)))

  return (
    <Modal open={open} onClose={onClose} title={`Correct ${receipt.doc_number}`} width={1000}
      footer={<div className="flex justify-end gap-2"><Button onClick={onClose}>Cancel</Button><Button variant="primary" disabled={reason.trim().length < 5 || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : 'Save correction'}</Button></div>}>
      <div className="space-y-3">
        <p className="text-xs text-slate-600">Quantities and costs are in the unit chosen on each line. A reduction is refused if that stock has already been sold or moved out of the receiving store.</p>
        <div className="overflow-x-auto">
          <table className="ui-table">
            <thead><tr><th>Product</th><th>Qty accepted</th><th>Unit</th><th>Unit cost</th><th>Batch</th><th>Expiry</th><th>Mfg date</th></tr></thead>
            <tbody>
              {lines.map((l) => (
                <tr key={l.id}>
                  <td className="text-xs font-semibold">{l.label}</td>
                  <td><Input type="number" min="0" step="any" className="w-24" value={l.qty_accepted} onChange={(e) => update(l.id, { qty_accepted: e.target.value })} /></td>
                  <td><Select value={l.uom_id} onChange={(e) => update(l.id, { uom_id: e.target.value })}>{l.uoms.map((u) => <option key={u.id} value={u.id}>{u.code}</option>)}</Select></td>
                  <td><Input type="number" min="0" step="any" className="w-28" value={l.unit_cost} onChange={(e) => update(l.id, { unit_cost: e.target.value })} /></td>
                  <td><Input className="w-28" value={l.batch_number} onChange={(e) => update(l.id, { batch_number: e.target.value })} /></td>
                  <td><Input type="date" value={l.expiry_date} onChange={(e) => update(l.id, { expiry_date: e.target.value })} /></td>
                  <td><Input type="date" value={l.manufacture_date} onChange={(e) => update(l.id, { manufacture_date: e.target.value })} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <Field label="Received date"><Input type="date" value={receivedAt} onChange={(e) => setReceivedAt(e.target.value)} /></Field>
          <Field label="Reason for the correction" required className="sm:col-span-2"><Textarea rows={2} value={reason} onChange={(e) => setReason(e.target.value)} placeholder="e.g. Received 250 strips, keyed 25" /></Field>
        </div>
        {save.isError && <InlineError error={save.error} />}
      </div>
    </Modal>
  )
}

type SaleDraft = { id: string; label: string; qty: string; price_incl: string; tax_rate: number }

/**
 * Corrects a posted sales invoice: its date, and each line's quantity and
 * VAT-inclusive unit price. The server reverses the sale's journals and
 * posts the corrected one; an invoice already with KRA is refused.
 */
export function CorrectSaleModal({ sale, open, onClose }: { sale: Sale; open: boolean; onClose: () => void }) {
  const queryClient = useQueryClient()
  const [lines, setLines] = useState<SaleDraft[]>([])
  const [postedAt, setPostedAt] = useState('')
  const [reason, setReason] = useState('')

  useEffect(() => {
    if (!open) return
    setReason('')
    setPostedAt(day(sale.posted_at))
    setLines((sale.lines ?? []).filter((l) => !l.is_bonus).map((l) => {
      const rate = Number(l.tax_rate || 0)
      return { id: l.id, label: `${l.product?.name ?? l.product_id} · ${l.uom?.code ?? ''}`, qty: num(l.qty), price_incl: (Number(l.unit_price) * (1 + rate / 100)).toFixed(2), tax_rate: rate }
    }))
  }, [open, sale])

  const save = useMutation({
    mutationFn: () => apiPost<Sale>(`/api/sales/${sale.id}/correct`, {
      reason,
      posted_at: postedAt || null,
      lines: lines.map((l) => ({ id: l.id, qty: l.qty, unit_price: (Number(l.price_incl) / (1 + l.tax_rate / 100)).toFixed(4) })),
    }),
    onSuccess: (s) => {
      toast.success(`Corrected ${s.doc_number}`, 'The sale\'s journals were reversed and re-posted with the corrected figures.')
      queryClient.invalidateQueries({ queryKey: ['sales'] })
      queryClient.invalidateQueries({ queryKey: ['inventory'] })
      onClose()
    },
  })

  const update = (id: string, patch: Partial<SaleDraft>) => setLines(lines.map((l) => (l.id === id ? { ...l, ...patch } : l)))
  const total = lines.reduce((sum, l) => sum + Number(l.qty || 0) * Number(l.price_incl || 0), 0)

  return (
    <Modal open={open} onClose={onClose} title={`Correct ${sale.doc_number}`} width={760}
      footer={<div className="flex justify-end gap-2"><Button onClick={onClose}>Cancel</Button><Button variant="primary" disabled={reason.trim().length < 5 || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : 'Save correction'}</Button></div>}>
      <div className="space-y-3">
        <table className="ui-table">
          <thead><tr><th>Item</th><th>Qty</th><th>Unit price (incl. VAT)</th><th className="text-right">Line total</th></tr></thead>
          <tbody>
            {lines.map((l) => (
              <tr key={l.id}>
                <td className="text-xs font-semibold">{l.label}{l.tax_rate > 0 && <div className="text-slate-500 font-normal">VAT {l.tax_rate}%</div>}</td>
                <td><Input type="number" min="0" step="any" className="w-24" value={l.qty} onChange={(e) => update(l.id, { qty: e.target.value })} /></td>
                <td><Input type="number" min="0" step="any" className="w-32" value={l.price_incl} onChange={(e) => update(l.id, { price_incl: e.target.value })} /></td>
                <td className="text-right tabular">{(Number(l.qty || 0) * Number(l.price_incl || 0)).toFixed(2)}</td>
              </tr>
            ))}
          </tbody>
          <tfoot><tr><td colSpan={3} className="text-right font-semibold">New total ≈</td><td className="text-right tabular font-semibold">{total.toFixed(2)}</td></tr></tfoot>
        </table>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <Field label="Invoice date"><Input type="date" value={postedAt} onChange={(e) => setPostedAt(e.target.value)} /></Field>
          <Field label="Reason for the correction" required className="sm:col-span-2"><Textarea rows={2} value={reason} onChange={(e) => setReason(e.target.value)} /></Field>
        </div>
        <p className="text-xs text-slate-600">Money already taken at the till stays as taken; any increase goes on the customer's account. An invoice already reported to KRA needs a credit note (return) instead.</p>
        {save.isError && <InlineError error={save.error} />}
      </div>
    </Modal>
  )
}
