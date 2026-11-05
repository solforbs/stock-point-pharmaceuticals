import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Printer } from 'lucide-react'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { PdfDownloadButton } from '../../components/PdfDownloadButton'
import { PostingImpact } from '../../components/PostingImpact'
import { Drawer } from '../../components/ui/Drawer'
import { ConfirmDialog, Modal } from '../../components/ui/Modal'
import { MoneyCell, QtyCell } from '../../components/ui/MoneyCell'
import { LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList } from '../../components/ui/primitives'
import { apiGet, apiPost } from '../../lib/api'
import { formatDate, formatDateTime } from '../../lib/format'
import { formatPct } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Sale } from '../../lib/types'
import { ReceiptSlip } from './ReceiptSlip'

export interface SaleDrawerProps {
  id: string | null
  onClose: () => void
}

export function SaleDrawer({ id, onClose }: SaleDrawerProps) {
  const queryClient = useQueryClient()
  const canVoid = usePermission('sale.void')
  const canReturn = usePermission('return.create')
  const showCost = usePermission('product.cost.view')
  const [voiding, setVoiding] = useState(false)
  const [reprinting, setReprinting] = useState(false)
  const sale = useQuery({ queryKey: ['sales', id], queryFn: () => apiGet<Sale>(`/api/sales/${id}`), enabled: !!id })

  const voidSale = useMutation({
    mutationFn: (reason: string) => apiPost<Sale>(`/api/sales/${id}/void`, { reason }),
    onSuccess: (data) => {
      toast.success(`Voided ${data.doc_number}`, 'Stock and journals were reversed; nothing was deleted.')
      queryClient.invalidateQueries({ queryKey: ['sales'] })
      queryClient.invalidateQueries({ queryKey: ['inventory'] })
      setVoiding(false)
    },
  })

  const s = sale.data
  return (
    <Drawer
      open={!!id}
      onClose={onClose}
      title={s ? `${s.doc_number}` : 'Sale Details'}
      subtitle={s ? `${s.sale_mode} · ${formatDateTime(s.posted_at)}` : undefined}
      width={740}
      actions={
        s ? (
          <div className="flex gap-2">
            <Button size="sm" onClick={() => setReprinting(true)}>
              <Printer size={12} /> Reprint receipt
            </Button>
            <PdfDownloadButton url={`/api/sales/${s.id}/pdf`} filename={s.doc_number} label="Invoice PDF" />
            {s.status === 'POSTED' && canReturn && (
              <Link to={`/sell/returns?sale=${s.id}`} className="inline-flex items-center h-8 px-3 rounded-lg border border-slate-300 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
                Return items
              </Link>
            )}
            {s.status === 'POSTED' && canVoid && <Button variant="danger" size="sm" onClick={() => setVoiding(true)}>Void sale</Button>}
          </div>
        ) : null
      }
    >
      {sale.isLoading && <LoadingSkeleton />}
      {s && (
        <div className="space-y-4">
          <div className="flex items-center gap-2">
            <StatusBadge status={s.status} />
            <StatusBadge status={s.sale_mode} />
            {s.void_reason && <span className="text-xs text-rose-700 font-semibold">Void reason: {s.void_reason}</span>}
          </div>
          <DescriptionList
            items={[
              { label: 'Customer', value: s.customer ? `${s.customer.name} (${s.customer.code ?? ''})` : 'Walk-in' },
              { label: 'Terminal', value: s.terminal_id ?? '—' },
              { label: 'Quote', value: s.quote_id ?? '—' },
              { label: 'Voided', value: s.voided_at ? formatDateTime(s.voided_at) : '—' },
            ]}
          />
          <table className="ui-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Product</th>
                <th className="text-right">Qty</th>
                <th className="text-right">Unit price</th>
                <th className="text-right">Disc</th>
                <th className="text-right">Tax</th>
                <th className="text-right">Total</th>
                {showCost && <th className="text-right">Cost</th>}
              </tr>
            </thead>
            <tbody>
              {(s.lines ?? []).map((line) => (
                <tr key={line.id}>
                  <td className="tabular">{line.line_number}</td>
                  <td>
                    <div>
                      {line.product?.name ?? line.product_id}
                      {line.is_bonus && <span className="ml-1 text-xs font-bold text-emerald-700">FREE</span>}
                    </div>
                    {line.batch_allocations && line.batch_allocations.length > 0 && (
                      <div className="text-xs text-slate-400 tabular">
                        {line.batch_allocations.map((a) => `${a.batch?.batch_number ?? a.batch_id.slice(0, 8)} ×${Number(a.qty_base)}${a.batch ? ` (exp ${formatDate(a.batch.expiry_date)})` : ''}`).join(', ')}
                      </div>
                    )}
                  </td>
                  <td className="text-right">
                    <QtyCell value={line.qty} />
                  </td>
                  <td className="text-right">
                    <MoneyCell value={line.unit_price} />
                  </td>
                  <td className="text-right tabular text-slate-500">{Number(line.discount_pct) > 0 ? formatPct(line.discount_pct) : '—'}</td>
                  <td className="text-right">
                    <MoneyCell value={line.tax_amount} />
                  </td>
                  <td className="text-right">
                    <MoneyCell value={line.line_total} />
                  </td>
                  {showCost && (
                    <td className="text-right">
                      <MoneyCell value={line.line_cost} muted />
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
          <div className="ml-auto w-72 grid grid-cols-[1fr_auto] gap-y-1.5 text-sm tabular">
            <span className="text-slate-500 font-medium">Subtotal</span>
            <MoneyCell value={s.subtotal} />
            <span className="text-slate-500 font-medium">Discount</span>
            <MoneyCell value={`-${s.discount_total}`} />
            <span className="text-slate-500 font-medium">Tax</span>
            <MoneyCell value={s.tax_total} />
            <span className="font-bold text-slate-900 pt-1.5 border-t border-slate-200">Grand Total</span>
            <MoneyCell value={s.grand_total} className="font-bold text-slate-900 pt-1.5 border-t border-slate-200" />
            {showCost && s.cost_total !== undefined && (
              <>
                <span className="text-slate-500 font-medium">Cost</span>
                <MoneyCell value={s.cost_total} muted />
              </>
            )}
          </div>
          <PostingImpact sourceId={s.id} />
        </div>
      )}
      {s && (
        <Modal open={reprinting} onClose={() => setReprinting(false)} title={`Reprint receipt ${s.doc_number}`} width={520}>
          <ReceiptSlip sale={s} payments={s.payments ?? []} servedBy={s.served_by} terminalId={s.terminal_id} customerName={s.customer?.name} etims={s} reprint />
          <div className="flex items-center gap-2 mt-4">
            <Button variant="primary" size="lg" className="flex-1" onClick={() => window.print()} autoFocus>
              <Printer size={14} /> Print
            </Button>
            <PdfDownloadButton size="md" url={`/api/sales/${s.id}/pdf`} filename={s.doc_number} label="Invoice PDF" />
          </div>
        </Modal>
      )}
      <ConfirmDialog
        open={voiding}
        title={`Void ${s?.doc_number ?? ''}?`}
        message="Voiding reverses stock and journals with an audited reason. The original document stays on record."
        confirmLabel="Void sale"
        danger
        requireReason="Reason for voiding"
        reasonMinLength={5}
        isPending={voidSale.isPending}
        onCancel={() => setVoiding(false)}
        onConfirm={(reason) => voidSale.mutate(reason)}
      />
    </Drawer>
  )
}
