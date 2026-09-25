import { useMutation, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link } from 'react-router-dom'
import { PdfDownloadButton } from '../../components/PdfDownloadButton'
import { Drawer } from '../../components/ui/Drawer'
import { MoneyCell, QtyCell } from '../../components/ui/MoneyCell'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList } from '../../components/ui/primitives'
import { apiPatch, apiPost } from '../../lib/api'
import { formatDate, formatDateTime } from '../../lib/format'
import { usePurchaseOrder, useSuppliers } from '../../lib/hooks'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Product, PurchaseOrder } from '../../lib/types'
import { ExpiryBadge } from '../inventory/ExpiryBadge'
import { NewPurchaseOrderDrawer, type PoLine } from './NewPurchaseOrderDrawer'
import { tradeTermsLabel } from './tradeTerms'

/** '10.0000' → '10', '418.5000' → '418.5' — decimals read like the user typed them. */
const plain = (v: string | null | undefined) => (v == null || v === '' ? '' : String(Number(v)))

export interface PurchaseOrderDrawerProps {
  id: string | null
  onClose: () => void
}

export function PurchaseOrderDrawer({ id, onClose }: PurchaseOrderDrawerProps) {
  const queryClient = useQueryClient()
  const canApprove = usePermission('po.approve')
  const canSend = usePermission('po.create')
  const po = usePurchaseOrder(id)
  const suppliers = useSuppliers()

  // Editing a draft (keyed or imported) before it is approved: supplier,
  // date and lines, through the same form that creates one.
  const [editing, setEditing] = useState(false)
  const [supplierId, setSupplierId] = useState('')
  const [expectedDate, setExpectedDate] = useState('')
  const [lines, setLines] = useState<PoLine[]>([])

  const act = useMutation({
    mutationFn: (kind: 'approve' | 'send') => apiPost<PurchaseOrder>(`/api/purchase-orders/${id}/${kind}`),
    onSuccess: (updated) => {
      toast.success(`${updated.doc_number} is now ${updated.status}`)
      queryClient.invalidateQueries({ queryKey: ['purchase-orders'] })
    },
  })

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPatch<PurchaseOrder>(`/api/purchase-orders/${id}`, {
        supplier_id: supplierId,
        expected_date: expectedDate || null,
        lines: lines.map((l) => ({
          product_id: l.product.id,
          uom_id: l.uom_id,
          qty_ordered: l.qty_ordered,
          unit_price: l.unit_price,
          trade_price: l.trade_price || null,
          discount_pct: l.trade_price ? l.discount_pct || '0' : null,
        })),
      }),
    onSuccess: (updated) => {
      toast.success(`${updated.doc_number} updated`)
      queryClient.invalidateQueries({ queryKey: ['purchase-orders'] })
      setEditing(false)
    },
  })

  function startEditing(p: PurchaseOrder) {
    setSupplierId(p.supplier_id)
    setExpectedDate(p.expected_date ?? '')
    setLines(
      (p.lines ?? []).map((l) => ({
        key: l.id,
        product: { id: l.product_id, code: l.product?.code ?? '', name: l.product?.name ?? l.product_id, uoms: l.product?.uoms } as Product,
        uom_id: l.uom_id,
        qty_ordered: plain(l.qty_ordered),
        unit_price: plain(l.unit_price),
        trade_price: plain(l.trade_price),
        discount_pct: plain(l.discount_pct),
      })),
    )
    save.reset()
    setEditing(true)
  }

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
            {p.rfq_id && (
              <Link to={`/buy/supplier-quotes?rfq=${p.rfq_id}`} className="text-xs font-semibold text-blue-600 hover:underline">
                Bid analysis
              </Link>
            )}
            {canSend && (p.status === 'DRAFT' || p.status === 'PENDING_APPROVAL') && (
              <Button size="sm" disabled={act.isPending} onClick={() => startEditing(p)}>
                Edit
              </Button>
            )}
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
                className="inline-flex items-center h-8 px-3 rounded-lg border border-slate-200 text-xs font-semibold text-slate-700 hover:bg-slate-100 hover:text-slate-900 transition-colors"
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
              <span className="text-xs text-slate-500 flex items-center gap-1.5">
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
                    <div className="font-semibold text-slate-900">{l.product?.name ?? l.product_id.slice(0, 8)}</div>
                    <div className="text-xs text-slate-500 font-mono">{l.product?.code}</div>
                  </td>
                  <td>{l.uom?.code ?? l.uom_id.slice(0, 8)}</td>
                  <td className="text-right"><QtyCell value={l.qty_ordered} /></td>
                  <td className="text-right">
                    <MoneyCell value={l.unit_price} />
                    {tradeTermsLabel(l.trade_price, l.discount_pct) && <div className="text-xs text-slate-500 tabular">{tradeTermsLabel(l.trade_price, l.discount_pct)}</div>}
                  </td>
                  <td className="text-right"><MoneyCell value={(Number(l.qty_ordered) * Number(l.unit_price)).toFixed(4)} /></td>
                </tr>
              ))}
            </tbody>
          </table>
          {(p.goods_receipts ?? []).length > 0 && (
            <div>
              <div className="ui-label">Goods receipts</div>
              <ul className="text-xs space-y-1.5">
                {p.goods_receipts!.map((g) => (
                  <li key={g.id} className="flex items-center gap-2">
                    <Link to={`/buy/goods-receipts?receipt=${g.id}`} className="tabular font-semibold font-mono text-blue-600 hover:text-blue-700 hover:underline">
                      {g.doc_number}
                    </Link>
                    <StatusBadge status={g.status} />
                    <span className="text-slate-500">{formatDateTime(g.received_at)}</span>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </div>
      )}

      <NewPurchaseOrderDrawer
        open={editing}
        onClose={() => setEditing(false)}
        suppliers={suppliers.data?.data ?? []}
        supplierId={supplierId}
        setSupplierId={setSupplierId}
        expectedDate={expectedDate}
        setExpectedDate={setExpectedDate}
        lines={lines}
        setLines={setLines}
        onSubmit={() => save.mutate()}
        isSubmitting={save.isPending}
        error={save.error}
        title={`Edit ${p?.doc_number ?? 'Purchase Order'}`}
        subtitle="Amend the draft — supplier, date and lines — before it is approved and sent"
        submitLabel="Save Changes"
      />
    </Drawer>
  )
}
