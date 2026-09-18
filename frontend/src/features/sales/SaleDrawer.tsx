import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Drawer } from '../../components/ui/Drawer'
import { ConfirmDialog } from '../../components/ui/Modal'
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
        s && s.status === 'POSTED' ? (
          <div className="flex gap-2">
            {canReturn && (
              <Link to={`/sell/returns?sale=${s.id}`} className="inline-flex items-center h-7 px-2.5 rounded-md border border-[var(--border-strong)] text-[11.5px] font-semibold hover:bg-[var(--surface-2)]">
                Return items
              </Link>
            )}
            {canVoid && <Button variant="danger" size="sm" onClick={() => setVoiding(true)}>Void sale</Button>}
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
            {s.void_reason && <span className="text-[11.5px] text-[var(--status-red)]">Void reason: {s.void_reason}</span>}
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
                      {line.is_bonus && <span className="ml-1 text-[10px] font-bold text-[var(--status-green)]">FREE</span>}
                    </div>
                    {line.batch_allocations && line.batch_allocations.length > 0 && (
                      <div className="text-[10.5px] text-[var(--text-muted)] tabular">
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
                  <td className="text-right tabular text-[var(--text-muted)]">{Number(line.discount_pct) > 0 ? formatPct(line.discount_pct) : '—'}</td>
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
          <div className="ml-auto w-72 grid grid-cols-[1fr_auto] gap-y-1 text-[12.5px] tabular">
            <span className="text-[var(--text-muted)]">Subtotal</span>
            <MoneyCell value={s.subtotal} />
            <span className="text-[var(--text-muted)]">Discount</span>
            <MoneyCell value={`-${s.discount_total}`} />
            <span className="text-[var(--text-muted)]">Tax</span>
            <MoneyCell value={s.tax_total} />
            <span className="font-bold">Grand total</span>
            <MoneyCell value={s.grand_total} className="font-bold" />
            {showCost && s.cost_total !== undefined && (
              <>
                <span className="text-[var(--text-muted)]">Cost</span>
                <MoneyCell value={s.cost_total} muted />
              </>
            )}
          </div>
        </div>
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
