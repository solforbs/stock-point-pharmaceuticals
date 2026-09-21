import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { QtyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, Field, Input, Select } from '../../components/ui/primitives'
import { apiGet, apiPost } from '../../lib/api'
import { formatDate, formatDateTime, titleCase } from '../../lib/format'
import { formatQty } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { DeliveryNote, Paginated } from '../../lib/types'

const STATUSES = ['DRAFT', 'DISPATCHED', 'DELIVERED', 'CANCELLED']

/** Delivery notes and proof of delivery — POD closes the note and touches nothing financial. */
export default function DeliveriesPage() {
  const [params, setParams] = useSearchParams()
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const selectedId = params.get('note')

  const list = useQuery({
    queryKey: ['delivery-notes', 'list', status, page],
    queryFn: () => apiGet<Paginated<DeliveryNote>>('/api/delivery-notes', { status, page, per_page: 50 }),
    placeholderData: (prev) => prev,
  })

  const columns: Column<DeliveryNote>[] = [
    { key: 'doc', header: 'Delivery note', render: (d) => <span className="font-semibold tabular">{d.doc_number}</span>, sortValue: (d) => d.doc_number },
    { key: 'order', header: 'Sales order', render: (d) => <span className="tabular">{d.sales_order?.doc_number ?? d.sales_order_id.slice(0, 8)}</span> },
    { key: 'customer', header: 'Customer', render: (d) => d.sales_order?.customer?.name ?? '—', sortValue: (d) => d.sales_order?.customer?.name ?? '' },
    { key: 'status', header: 'Status', render: (d) => <StatusBadge status={d.status} /> },
    { key: 'vehicle', header: 'Vehicle / driver', render: (d) => `${d.vehicle_reg ?? '—'} · ${d.driver_name ?? '—'}` },
    { key: 'dispatched', header: 'Dispatched', render: (d) => formatDateTime(d.dispatched_at), sortValue: (d) => d.dispatched_at ?? '' },
    { key: 'delivered', header: 'Delivered', render: (d) => (d.delivered_at ? <span className="tabular">{formatDateTime(d.delivered_at)}<div className="text-xs text-slate-500 tabular">{d.received_by_name}</div></span> : '—') },
  ]

  return (
    <Page>
      <PageHeader parent="Warehouse" title="Deliveries" subtitle="Every delivery note for the branch. Open a dispatched note to record who received the goods." />
      <FilterBar>
        <div id="tour-deliveries-status" className="w-full sm:w-56">
          <Field label="Status"><Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}><option value="">All</option>{STATUSES.map((s) => (<option key={s} value={s}>{titleCase(s)}</option>))}</Select></Field>
        </div>
      </FilterBar>
      <div id="tour-deliveries-table" className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(d) => d.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(d) => setParams({ note: d.id })} selectedKey={selectedId} emptyTitle="No delivery notes" />
        <Pagination page={list.data} onPage={setPage} />
      </div>
      <DeliveryNoteDrawer id={selectedId} onClose={() => setParams({})} />
    </Page>
  )
}

function DeliveryNoteDrawer({ id, onClose }: { id: string | null; onClose: () => void }) {
  const queryClient = useQueryClient()
  const canPod = usePermission('warehouse.dispatch')
  const [receivedBy, setReceivedBy] = useState('')
  const note = useQuery({ queryKey: ['delivery-notes', id], queryFn: () => apiGet<DeliveryNote>(`/api/delivery-notes/${id}`), enabled: !!id })

  const pod = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<DeliveryNote>(`/api/delivery-notes/${id}/pod`, { received_by_name: receivedBy }),
    onSuccess: (dn) => {
      toast.success(`${dn.doc_number} delivered`, `Received by ${dn.received_by_name}`)
      queryClient.invalidateQueries({ queryKey: ['delivery-notes'] })
      queryClient.invalidateQueries({ queryKey: ['sales-orders'] })
      setReceivedBy('')
    },
  })

  const d = note.data
  return (
    <Drawer open={!!id} onClose={onClose} title={d?.doc_number ?? 'Delivery note'} subtitle={d ? `${d.sales_order?.customer?.name ?? ''} · order ${d.sales_order?.doc_number ?? ''}` : undefined} width={760}>
      {note.isLoading && <LoadingSkeleton />}
      {note.isError && <InlineError error={note.error} />}
      {d && (
        <div className="space-y-4">
          <div className="flex items-center gap-2 flex-wrap">
            <StatusBadge status={d.status} />
            {d.sale_id && <Link to={`/sell/invoices?sale=${d.sale_id}`} className="text-xs text-blue-600 hover:text-blue-700 hover:underline font-semibold">Open the posted invoice</Link>}
            <Link to={`/sell/sales-orders?order=${d.sales_order_id}`} className="text-xs text-blue-600 hover:text-blue-700 hover:underline font-semibold">Open sales order</Link>
          </div>
          <DescriptionList items={[{ label: 'Vehicle', value: d.vehicle_reg ?? '—' }, { label: 'Driver', value: `${d.driver_name ?? '—'}${d.driver_phone ? ` (${d.driver_phone})` : ''}` }, { label: 'Dispatched', value: formatDateTime(d.dispatched_at) }, { label: 'Delivered', value: d.delivered_at ? `${formatDateTime(d.delivered_at)} · received by ${d.received_by_name ?? ''}` : '—' }]} />
          <table className="ui-table">
            <thead><tr><th>Product</th><th className="text-right">Qty (base)</th><th>Batches</th></tr></thead>
            <tbody>
              {(d.lines ?? []).map((l) => (
                <tr key={l.id}>
                  <td>{l.product?.name ?? l.product_id.slice(0, 8)}</td>
                  <td className="text-right"><QtyCell value={l.qty_base ?? l.qty_dispatched_base ?? (l.batch_allocations ?? []).reduce((s, a) => s + Number(a.qty_base), 0).toFixed(4)} /></td>
                  <td className="tabular text-xs">{(l.batch_allocations ?? []).map((a) => `${a.batch?.batch_number ?? a.batch_id.slice(0, 8)} ×${formatQty(a.qty_base)}${a.batch ? ` (exp ${formatDate(a.batch.expiry_date)})` : ''}`).join(', ') || '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>
          {d.status === 'DISPATCHED' && canPod && (
            <div className="ui-card p-4 space-y-3">
              <Field label="Received by (name, role)" required><Input value={receivedBy} onChange={(e) => setReceivedBy(e.target.value)} placeholder="Sister Akai, Pharmacy Stores" /></Field>
              {pod.isError && <InlineError error={pod.error} />}
              <Button variant="success" disabled={!receivedBy.trim() || pod.isPending} onClick={() => pod.mutate()}>{pod.isPending ? 'Saving…' : 'Confirm delivery'}</Button>
            </div>
          )}
        </div>
      )}
    </Drawer>
  )
}
