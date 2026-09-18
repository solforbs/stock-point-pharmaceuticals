import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { QtyCell } from '../../components/ui/MoneyCell'
import { Page, PageHeader } from '../../components/ui/PageHeader'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Card } from '../../components/ui/primitives'
import { apiGet, apiPost } from '../../lib/api'
import { formatDate, formatDateTime } from '../../lib/format'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Paginated, PickingList, PickingListLine, SalesOrder } from '../../lib/types'

/** Part 10.2 — pick lists in FEFO / location order; lines picked (short allowed), then completed for dispatch. Open lists can be resumed. */
export default function PickListsPage() {
  const [params, setParams] = useSearchParams()
  const queryClient = useQueryClient()
  const canPick = usePermission('warehouse.pick')
  const [picked, setPicked] = useState<Record<string, string>>({})
  const preselectedOrder = params.get('order')
  const listId = params.get('list')

  const orders = useQuery({ queryKey: ['sales-orders', 'pickable'], queryFn: () => apiGet<Paginated<SalesOrder>>('/api/sales-orders', { per_page: 200 }) })
  const pickable = (orders.data?.data ?? []).filter((o) => ['CONFIRMED', 'IN_PROGRESS', 'PARTIALLY_FULFILLED'].includes(o.status))
  const openLists = useQuery({ queryKey: ['picking-lists', 'open'], queryFn: () => apiGet<Paginated<PickingList>>('/api/picking-lists', { per_page: 100 }) })
  const resumable = (openLists.data?.data ?? []).filter((l) => l.status === 'PENDING' || l.status === 'IN_PROGRESS')
  const list = useQuery({ queryKey: ['picking-lists', listId], queryFn: () => apiGet<PickingList>(`/api/picking-lists/${listId}`), enabled: !!listId })

  const generate = useMutation({
    meta: { silent: true },
    mutationFn: (orderId: string) => apiPost<PickingList>(`/api/sales-orders/${orderId}/pick`),
    onSuccess: (pl) => {
      setPicked({})
      toast.success(`Pick list ${pl.doc_number} generated`, 'Lines are in FEFO / location order.')
      queryClient.invalidateQueries({ queryKey: ['sales-orders'] })
      queryClient.invalidateQueries({ queryKey: ['picking-lists'] })
      setParams({ list: pl.id })
    },
  })

  const pickLine = useMutation({
    mutationFn: ({ lineId, qty }: { lineId: string; qty: string }) => apiPost<PickingListLine>(`/api/picking-lists/${listId}/lines/${lineId}/pick`, { qty_picked_base: qty }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['picking-lists', listId] }),
  })

  const complete = useMutation({
    mutationFn: () => apiPost<PickingList>(`/api/picking-lists/${listId}/complete`),
    onSuccess: (pl) => {
      toast.success(`${pl.doc_number} completed`, 'Ready to dispatch.')
      queryClient.invalidateQueries({ queryKey: ['picking-lists'] })
      queryClient.invalidateQueries({ queryKey: ['sales-orders'] })
    },
  })

  const orderColumns: Column<SalesOrder>[] = [
    { key: 'doc', header: 'Order', render: (o) => <span className="font-semibold tabular">{o.doc_number}</span> },
    { key: 'customer', header: 'Customer', render: (o) => o.customer?.name ?? '—' },
    { key: 'status', header: 'Status', render: (o) => <StatusBadge status={o.status} /> },
    { key: 'required', header: 'Required', render: (o) => formatDate(o.required_date) },
    { key: 'lines', header: 'Lines', align: 'right', render: (o) => <span className="tabular">{o.lines_count ?? '—'}</span> },
    { key: 'act', header: '', align: 'right', render: (o) => <Button size="sm" variant="primary" disabled={!canPick || generate.isPending} onClick={() => generate.mutate(o.id)}>Generate pick list</Button> },
  ]
  const listColumns: Column<PickingList>[] = [
    { key: 'doc', header: 'Pick list', render: (l) => <span className="font-semibold tabular">{l.doc_number}</span> },
    { key: 'order', header: 'Order', render: (l) => <span className="tabular">{l.sales_order?.doc_number ?? ''}</span> },
    { key: 'customer', header: 'Customer', render: (l) => l.sales_order?.customer?.name ?? '—' },
    { key: 'status', header: 'Status', render: (l) => <StatusBadge status={l.status} /> },
    { key: 'started', header: 'Started', render: (l) => formatDateTime(l.started_at) },
    { key: 'act', header: '', align: 'right', render: (l) => <Button size="sm" onClick={() => setParams({ list: l.id })}>Resume</Button> },
  ]

  const pl = list.data
  const allPicked = !!pl && pl.lines.every((l) => l.status !== 'PENDING')

  return (
    <Page>
      <PageHeader parent="Warehouse" title="Pick Lists" subtitle="Generate a pick list from a confirmed order, pick each line, then complete it for dispatch." />
      {generate.isError && <InlineError error={generate.error} className="mb-3" />}
      <div className="grid gap-4 xl:grid-cols-[1fr_1fr]">
        <div className="space-y-4">
          <Card title="Orders awaiting picking">
            <DataTable columns={orderColumns} rows={orders.data ? pickable : undefined} rowKey={(o) => o.id} isLoading={orders.isLoading} error={orders.error} emptyTitle="No confirmed orders" rowClassName={(o) => (o.id === preselectedOrder ? 'is-selected' : '')} />
          </Card>
          <Card title="Open pick lists">
            <DataTable columns={listColumns} rows={openLists.data ? resumable : undefined} rowKey={(l) => l.id} isLoading={openLists.isLoading} error={openLists.error} emptyTitle="Nothing in progress" selectedKey={listId} />
          </Card>
        </div>
        <Card title={pl ? `Pick list ${pl.doc_number}` : 'Pick list'} actions={pl ? <StatusBadge status={pl.status} /> : null}>
          {!listId ? (
            <div className="p-6 text-[12px] text-[var(--text-muted)]">Generate a pick list from an order, or resume an open one.</div>
          ) : list.isLoading ? (
            <LoadingSkeleton />
          ) : list.isError ? (
            <div className="p-4"><InlineError error={list.error} /></div>
          ) : pl ? (
            <div className="p-4 space-y-3">
              <div className="text-[12px] text-[var(--text-secondary)]">Order {pl.sales_order?.doc_number ?? ''} · {pl.sales_order?.customer?.name ?? ''}</div>
              <table className="ui-table">
                <thead><tr><th>#</th><th>Product</th><th>Batch</th><th className="text-right">To pick</th><th>Picked</th><th>Status</th><th /></tr></thead>
                <tbody>
                  {pl.lines.map((l, i) => (
                    <tr key={l.id}>
                      <td className="tabular">{i + 1}</td>
                      <td>{l.product?.name ?? l.product_id.slice(0, 8)}</td>
                      <td className="tabular">{l.batch?.batch_number ?? l.batch_id.slice(0, 8)}{l.batch && <div className="text-[10.5px] text-[var(--text-muted)]">exp {formatDate(l.batch.expiry_date)}</div>}</td>
                      <td className="text-right"><QtyCell value={l.qty_to_pick_base} /></td>
                      <td>
                        {l.status === 'PENDING' ? (
                          <input value={picked[l.id] ?? String(Number(l.qty_to_pick_base))} onChange={(e) => setPicked({ ...picked, [l.id]: e.target.value.replace(/[^\d.]/g, '') })} className="ui-input h-7 w-24 tabular text-right" />
                        ) : (
                          <QtyCell value={l.qty_picked_base} />
                        )}
                      </td>
                      <td><StatusBadge status={l.status} /></td>
                      <td className="text-right">
                        {l.status === 'PENDING' && <Button size="sm" disabled={!canPick || pickLine.isPending || pl.status === 'COMPLETED'} onClick={() => pickLine.mutate({ lineId: l.id, qty: picked[l.id] ?? String(Number(l.qty_to_pick_base)) })}>Pick</Button>}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
              <div className="flex items-center justify-between text-[11.5px] text-[var(--text-muted)]">
                <span>Started {formatDateTime(pl.started_at)}{pl.completed_at ? ` · completed ${formatDateTime(pl.completed_at)}` : ''}{(pl.delivery_notes ?? []).length > 0 ? ` · delivery ${pl.delivery_notes!.map((d) => `${d.doc_number} (${d.status})`).join(', ')}` : ''}</span>
                {pl.status === 'COMPLETED' ? (
                  (pl.delivery_notes ?? []).length > 0 ? (
                    <Link to={`/warehouse/deliveries?note=${pl.delivery_notes![0].id}`} className="text-[var(--color-navy)] underline font-semibold">Open delivery note</Link>
                  ) : (
                    <Link to={`/warehouse/dispatch?order=${pl.sales_order_id}`} className="text-[var(--color-navy)] underline font-semibold">Go to dispatch</Link>
                  )
                ) : (
                  <Button variant="success" disabled={!allPicked || !canPick || complete.isPending} onClick={() => complete.mutate()} title={allPicked ? undefined : 'Pick every line first (short picks are allowed)'}>{complete.isPending ? 'Completing…' : 'Complete picking'}</Button>
                )}
              </div>
            </div>
          ) : null}
        </Card>
      </div>
    </Page>
  )
}
