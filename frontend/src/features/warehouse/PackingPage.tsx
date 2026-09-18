import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { QtyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { ErrorState, InlineError, LoadingSkeleton, NoAccess } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, Field, Input, Select, Textarea } from '../../components/ui/primitives'
import { apiGet, apiPost, getApiError } from '../../lib/api'
import { formatDateTime } from '../../lib/format'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { OrderRef, Paginated, PickingList } from '../../lib/types'

type PackingFields = {
  packed_at: string | null
  packed_by: number | null
  package_count: number | null
  total_weight_kg: string | null
  packing_notes: string | null
  packer?: { id: number; name: string } | null
}

type PackingQueueRow = PackingFields & {
  id: string
  doc_number: string
  status: string
  sales_order_id: string
  completed_at: string | null
  lines_count: number
  sales_order?: OrderRef | null
}

type PackingListDetail = PickingList & PackingFields

/** Part 10.6 — packing sits between a completed pick and dispatch. Recorded here; dispatch does not require it. */
export default function PackingPage() {
  const canPack = usePermission('warehouse.pick')
  const [params, setParams] = useSearchParams()
  const [packed, setPacked] = useState<'' | 'no' | 'yes'>('')
  const [page, setPage] = useState(1)
  const selectedId = params.get('list')

  const queue = useQuery({
    queryKey: ['packing', 'queue', packed, page],
    queryFn: () => apiGet<Paginated<PackingQueueRow>>('/api/packing/queue', { packed: packed || undefined, page, per_page: 50 }),
    placeholderData: (prev) => prev,
    enabled: canPack,
  })

  if (!canPack) return <NoAccess permission="warehouse.pick" />

  const columns: Column<PackingQueueRow>[] = [
    { key: 'doc', header: 'Pick list', render: (r) => <span className="font-semibold tabular">{r.doc_number}</span>, sortValue: (r) => r.doc_number },
    { key: 'order', header: 'Sales order', render: (r) => <span className="tabular">{r.sales_order?.doc_number ?? '—'}</span> },
    { key: 'customer', header: 'Customer', render: (r) => r.sales_order?.customer?.name ?? '—', sortValue: (r) => r.sales_order?.customer?.name ?? '' },
    { key: 'lines', header: 'Lines', align: 'right', render: (r) => <span className="tabular">{r.lines_count}</span> },
    { key: 'completed', header: 'Picked', render: (r) => formatDateTime(r.completed_at), sortValue: (r) => r.completed_at ?? '' },
    { key: 'state', header: 'Packing', render: (r) => (r.packed_at ? <StatusBadge status="PACKED" tone="green" label="Packed" /> : <StatusBadge status="AWAITING" tone="amber" label="Awaiting packing" />) },
    { key: 'packages', header: 'Packages', align: 'right', render: (r) => <span className="tabular">{r.package_count ?? '—'}</span> },
    { key: 'weight', header: 'Weight (kg)', align: 'right', render: (r) => (r.total_weight_kg ? <QtyCell value={r.total_weight_kg} /> : <span className="text-[var(--text-muted)]">—</span>) },
  ]

  return (
    <Page>
      <PageHeader
        parent="Warehouse"
        title="Packing"
        subtitle="Completed pick lists waiting to go out. Record how many packages each order went into before dispatch."
        actions={<Link to="/warehouse/dispatch"><Button>Go to dispatch</Button></Link>}
      />
      <FilterBar>
        <Field label="Show" className="w-52">
          <Select value={packed} onChange={(e) => { setPacked(e.target.value as '' | 'no' | 'yes'); setPage(1) }}>
            <option value="">All awaiting dispatch</option>
            <option value="no">Awaiting packing</option>
            <option value="yes">Packed</option>
          </Select>
        </Field>
      </FilterBar>
      <div className="ui-card">
        <DataTable
          columns={columns}
          rows={queue.data?.data}
          rowKey={(r) => r.id}
          isLoading={queue.isLoading}
          error={queue.error}
          onRetry={() => queue.refetch()}
          onRowClick={(r) => setParams({ list: r.id })}
          selectedKey={selectedId}
          emptyTitle="Nothing to pack"
          emptyHint={<>Completed pick lists appear here until they are dispatched. Start from <Link to="/warehouse/pick-lists" className="underline">Pick lists</Link>.</>}
        />
        <Pagination page={queue.data} onPage={setPage} />
      </div>
      <Drawer open={!!selectedId} onClose={() => setParams({})} title="Pack order" width={680}>
        {selectedId && <PackDrawer listId={selectedId} onDone={() => setParams({})} />}
      </Drawer>
    </Page>
  )
}

function PackDrawer({ listId, onDone }: { listId: string; onDone: () => void }) {
  const detail = useQuery({ queryKey: ['picking-lists', listId], queryFn: () => apiGet<PackingListDetail>(`/api/picking-lists/${listId}`) })

  if (detail.isLoading) return <LoadingSkeleton rows={6} />
  if (detail.error || !detail.data) return <ErrorState error={detail.error} onRetry={() => detail.refetch()} />
  return <PackForm key={`${detail.data.id}:${detail.data.packed_at ?? ''}`} list={detail.data} onDone={onDone} />
}

function PackForm({ list, onDone }: { list: PackingListDetail; onDone: () => void }) {
  const queryClient = useQueryClient()
  const [packageCount, setPackageCount] = useState(list.package_count ? String(list.package_count) : '')
  const [weight, setWeight] = useState(list.total_weight_kg ? String(Number(list.total_weight_kg)) : '')
  const [notes, setNotes] = useState(list.packing_notes ?? '')
  const listId = list.id

  const pack = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<PackingQueueRow>(`/api/picking-lists/${listId}/pack`, { package_count: Number(packageCount), total_weight_kg: weight === '' ? null : weight, packing_notes: notes || null }),
    onSuccess: (r) => {
      toast.success(`${r.doc_number} packed`, `${r.package_count} package${r.package_count === 1 ? '' : 's'} ready for dispatch.`)
      queryClient.invalidateQueries({ queryKey: ['packing'] })
      queryClient.invalidateQueries({ queryKey: ['picking-lists'] })
      onDone()
    },
  })
  const err = pack.isError ? getApiError(pack.error) : null
  const dispatched = (list.delivery_notes ?? []).some((n) => n.status === 'DISPATCHED' || n.status === 'DELIVERED')

  const lineColumns: Column<PickingList['lines'][number]>[] = [
    { key: 'product', header: 'Product', render: (l) => <><span className="font-semibold">{l.product?.name ?? '—'}</span><div className="text-[10.5px] text-[var(--text-muted)] tabular">{l.product?.code}</div></> },
    { key: 'batch', header: 'Batch', render: (l) => <span className="tabular">{l.batch?.batch_number ?? '—'}</span> },
    { key: 'qty', header: 'Picked (base)', align: 'right', render: (l) => <QtyCell value={l.qty_picked_base} /> },
    { key: 'status', header: 'Line', render: (l) => <StatusBadge status={l.status} /> },
  ]

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2"><span className="font-bold tabular">{list.doc_number}</span><StatusBadge status={list.status} />{list.packed_at && <StatusBadge status="PACKED" tone="green" label="Packed" />}</div>
      <DescriptionList
        items={[
          { label: 'Sales order', value: list.sales_order?.doc_number ?? '—' },
          { label: 'Customer', value: list.sales_order?.customer?.name ?? '—' },
          { label: 'Picked', value: formatDateTime(list.completed_at) },
          { label: 'Last packed', value: list.packed_at ? `${formatDateTime(list.packed_at)}${list.packer ? ` by ${list.packer.name}` : ''}` : 'Not yet' },
        ]}
      />
      <div className="ui-card">
        <DataTable columns={lineColumns} rows={list.lines} rowKey={(l) => l.id} emptyTitle="No lines on this pick list" />
      </div>
      {dispatched ? (
        <p className="text-[12px] text-[var(--text-muted)]">This order has already been dispatched; its packing record is final.</p>
      ) : list.status !== 'COMPLETED' ? (
        <p className="text-[12px] text-[var(--text-muted)]">Only a completed pick can be packed. Finish picking on the <Link to="/warehouse/pick-lists" className="underline">Pick lists</Link> page first.</p>
      ) : (
        <>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <Field label="Packages" required error={err?.errors.package_count?.[0]}><Input inputMode="numeric" className="tabular" value={packageCount} onChange={(e) => setPackageCount(e.target.value.replace(/\D/g, ''))} placeholder="e.g. 3" /></Field>
            <Field label="Total weight (kg)" error={err?.errors.total_weight_kg?.[0]}><Input inputMode="decimal" className="tabular" value={weight} onChange={(e) => setWeight(e.target.value.replace(/[^\d.]/g, ''))} placeholder="Optional" /></Field>
          </div>
          <Field label="Packing notes" error={err?.errors.packing_notes?.[0]}><Textarea rows={3} value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="Cool box, fragile, cartons sealed…" /></Field>
          {err && !Object.keys(err.errors).length && <InlineError error={pack.error} />}
          <div className="flex justify-between gap-2">
            <Link to={`/warehouse/dispatch?order=${list.sales_order_id}`} className="text-[12px] text-[var(--color-navy)] underline self-center">Dispatch this order</Link>
            <Button variant="primary" disabled={!packageCount || Number(packageCount) < 1 || pack.isPending} onClick={() => pack.mutate()}>{pack.isPending ? 'Saving…' : list.packed_at ? 'Correct packing' : 'Mark packed'}</Button>
          </div>
        </>
      )}
    </div>
  )
}
