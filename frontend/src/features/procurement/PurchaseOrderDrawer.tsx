import { useMutation, useQueryClient } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import { PdfDownloadButton } from '../../components/PdfDownloadButton'
import { Drawer } from '../../components/ui/Drawer'
import { MoneyCell, QtyCell } from '../../components/ui/MoneyCell'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList } from '../../components/ui/primitives'
import { apiPost } from '../../lib/api'
import { formatDate, formatDateTime } from '../../lib/format'
import { usePurchaseOrder } from '../../lib/hooks'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { PurchaseOrder } from '../../lib/types'
import { ExpiryBadge } from '../inventory/ExpiryBadge'

export interface PurchaseOrderDrawerProps {
  id: string | null
  onClose: () => void
}

export function PurchaseOrderDrawer({ id, onClose }: PurchaseOrderDrawerProps) {
  const queryClient = useQueryClient()
  const canApprove = usePermission('po.approve')
  const canSend = usePermission('po.create')
  const po = usePurchaseOrder(id)

  const act = useMutation({
    mutationFn: (kind: 'approve' | 'send') => apiPost<PurchaseOrder>(`/api/purchase-orders/${id}/${kind}`),
    onSuccess: (updated) => {
      toast.success(`${updated.doc_number} is now ${updated.status}`)
      queryClient.invalidateQueries({ queryKey: ['purchase-orders'] })
    },
  })

  const p = po.data
  const total = (p?.lines ?? []).reduce((s, l) => s + Number(l.qty_ordered) * Number(l.unit_price), 0)

  return (
    <Drawer
      open={!!id}
      onClose={onClose}
      title={p?.doc_number ?? 'Purchase Order'}
      subtitle={p ? `${p.supplier?.name ?? ''} · ${formatDateTime(p.created_at)}` : undefined}
      width={780}
      actions={
        p ? (
          <div className="flex items-center gap-2">
            <PdfDownloadButton url={`/api/purchase-orders/${p.id}/pdf`} filename={p.doc_number} label="Download PO PDF" />
            {canApprove && (p.status === 'DRAFT' || p.status === 'PENDING_APPROVAL') && (
              <Button size="sm" variant="success" disabled={act.isPending} onClick={() => act.mutate('approve')}>
                Approve
              </Button>
            )}
            {canSend && p.status === 'APPROVED' && (
              <Button size="sm" variant="primary" disabled={act.isPending} onClick={() => act.mutate('send')}>
                Send to Supplier
              </Button>
            )}
            {['SENT', 'APPROVED', 'PARTIALLY_RECEIVED'].includes(p.status) && (
              <Link
                to={`/buy/goods-receipts?po=${p.id}`}
                className="inline-flex items-center h-7 px-2.5 rounded-md border border-[var(--border-strong)] text-[11.5px] font-semibold hover:bg-[var(--surface-2)]"
              >
                Receive Goods
              </Link>
            )}
          </div>
        ) : null
      }
    >
      {po.isLoading && <LoadingSkeleton />}
      {po.isError && <InlineError error={po.error} />}
      {p && (
        <div className="space-y-4">
          <div className="flex items-center gap-2">
            <StatusBadge status={p.status} />
            {p.supplier?.licence_expiry && (
              <span className="text-[11.5px] text-[var(--text-muted)] flex items-center gap-1.5">
                Supplier licence <ExpiryBadge date={p.supplier.licence_expiry} />
              </span>
            )}
          </div>
          <DescriptionList
            items={[
              { label: 'Expected', value: formatDate(p.expected_date) },
              { label: 'Sent', value: formatDateTime(p.sent_at) },
              { label: 'Order value', value: <MoneyCell value={total.toFixed(4)} symbol /> },
            ]}
          />
          <table className="ui-table">
            <thead>
              <tr>
                <th>Product</th>
                <th>UOM</th>
                <th className="text-right">Qty</th>
                <th className="text-right">Unit price</th>
                <th className="text-right">Line total</th>
              </tr>
            </thead>
            <tbody>
              {(p.lines ?? []).map((l) => (
                <tr key={l.id}>
                  <td>
                    <div className="font-semibold text-[var(--text)]">{l.product?.name ?? l.product_id.slice(0, 8)}</div>
                    <div className="text-[10.5px] text-[var(--text-muted)]">{l.product?.code}</div>
                  </td>
                  <td>{l.uom?.code ?? l.uom_id.slice(0, 8)}</td>
                  <td className="text-right"><QtyCell value={l.qty_ordered} /></td>
                  <td className="text-right"><MoneyCell value={l.unit_price} /></td>
                  <td className="text-right"><MoneyCell value={(Number(l.qty_ordered) * Number(l.unit_price)).toFixed(4)} /></td>
                </tr>
              ))}
            </tbody>
          </table>
          {(p.goods_receipts ?? []).length > 0 && (
            <div>
              <div className="ui-label">Goods receipts</div>
              <ul className="text-[12px] space-y-1">
                {p.goods_receipts!.map((g) => (
                  <li key={g.id} className="flex items-center gap-2">
                    <Link to={`/buy/goods-receipts?receipt=${g.id}`} className="tabular font-semibold text-[var(--color-navy)] underline">
                      {g.doc_number}
                    </Link>
                    <StatusBadge status={g.status} />
                    <span className="text-[var(--text-muted)]">{formatDateTime(g.received_at)}</span>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>
      )}
    </Drawer>
  )
}
