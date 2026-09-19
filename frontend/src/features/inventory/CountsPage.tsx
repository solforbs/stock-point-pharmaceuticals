import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { ClipboardList, Plus, X } from 'lucide-react'
import { useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { ProductSearch } from '../../components/ProductSearch'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { MoneyCell, QtyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DrawerFooter, Field, FormSection, PrimaryAction, Select } from '../../components/ui/primitives'
import { apiGet, apiPost, getApiError } from '../../lib/api'
import { formatDate, formatDateTime, titleCase } from '../../lib/format'
import { useStores } from '../../lib/hooks'
import { formatMoney } from '../../lib/money'
import { usePermissions } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Paginated, Product, StockCount, StockCountLine } from '../../lib/types'

const STATUSES = ['PLANNED', 'COUNTING', 'REVIEW', 'APPROVED', 'CLOSED']

/** Part 7.7 — batch-level counts: PLANNED → COUNTING → REVIEW → APPROVED → CLOSED, with a second approver above the threshold. */
export default function CountsPage() {
  const [params, setParams] = useSearchParams()
  const queryClient = useQueryClient()
  const perms = usePermissions()
  const stores = useStores()
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const [storeId, setStoreId] = useState('')
  const [products, setProducts] = useState<Product[]>([])
  const selectedId = params.get('count')

  const list = useQuery({
    queryKey: ['counts', 'list', status, page],
    queryFn: () => apiGet<Paginated<StockCount>>('/api/inventory/counts', { status, page, per_page: 50 }),
    placeholderData: (prev) => prev,
  })

  const create = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<StockCount>('/api/inventory/counts', { store_id: storeId, product_ids: products.length ? products.map((p) => p.id) : null }),
    onSuccess: (c) => {
      toast.success(`Count ${c.doc_number} planned`, `${c.lines?.length ?? 0} batch lines to count.`)
      queryClient.invalidateQueries({ queryKey: ['counts'] })
      setCreating(false)
      setProducts([])
      setParams({ count: c.id })
    },
  })

  const columns: Column<StockCount>[] = [
    { key: 'doc', header: 'Document', render: (c) => <span className="font-semibold tabular">{c.doc_number}</span>, sortValue: (c) => c.doc_number },
    { key: 'store', header: 'Store', render: (c) => c.store?.code ?? '—' },
    { key: 'status', header: 'Status', render: (c) => <StatusBadge status={c.status} /> },
    { key: 'lines', header: 'Lines', align: 'right', render: (c) => <span className="tabular">{c.lines_count ?? '—'}</span> },
    { key: 'created', header: 'Created', render: (c) => formatDateTime(c.created_at), sortValue: (c) => c.created_at ?? '' },
    { key: 'approved', header: 'Approved', render: (c) => formatDateTime(c.approved_at) },
  ]

  return (
    <Page>
      <PageHeader
        parent="Inventory & Audits"
        title="Physical Stock Counts (Stocktaking)"
        subtitle="Blind batch-level stocktaking with variance analysis and dual-control approval"
        actions={
          perms.has('stock.count.enter') ? (
            <PrimaryAction icon={Plus} onClick={() => setCreating(true)}>
              Plan a count
            </PrimaryAction>
          ) : null
        }
      />
      <FilterBar>
        <Field label="Status">
          <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
            <option value="">All Statuses</option>
            {STATUSES.map((s) => (<option key={s} value={s}>{titleCase(s)}</option>))}
          </Select>
        </Field>
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(c) => c.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(c) => setParams({ count: c.id })} selectedKey={selectedId} emptyTitle="No counts" />
        <Pagination page={list.data} onPage={setPage} />
      </div>

      <Drawer
        open={creating}
        onClose={() => setCreating(false)}
        title="Plan a Stock Count"
        subtitle="Initiate a blind batch verification session for a store location"
        width={680}
        footer={
          <DrawerFooter
            badge={products.length > 0 ? `${products.length} products selected` : 'Store-wide count'}
            onCancel={() => setCreating(false)}
            onSubmit={() => create.mutate()}
            submitLabel="Plan count"
            disabled={!storeId || create.isPending}
            isPending={create.isPending}
          />
        }
      >
        <div className="space-y-4">
          <FormSection
            title="Count Scope & Location"
            description="Select store location and optionally scope to specific products"
            icon={ClipboardList}
            badge="Blind Count"
          >
            <div className="space-y-3.5">
              <Field label="Target Store Location" required>
                <Select value={storeId} onChange={(e) => setStoreId(e.target.value)}>
                  <option value="">Choose store…</option>
                  {(stores.data ?? []).map((s) => (<option key={s.id} value={s.id}>{s.code} · {s.name}</option>))}
                </Select>
              </Field>
              <Field label="Limit to Products (Optional)" hint="Leave empty to count every single batch in the store.">
                <ProductSearch onSelect={(p) => setProducts((prev) => (prev.some((x) => x.id === p.id) ? prev : [...prev, p]))} />
                {products.length > 0 && (
                  <div className="flex flex-wrap gap-1.5 mt-2">
                    {products.map((p) => (
                      <span key={p.id} className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-blue-50 border border-blue-200 text-blue-800 text-xs font-semibold">
                        {p.name}
                        <button type="button" onClick={() => setProducts(products.filter((x) => x.id !== p.id))} aria-label="Remove" className="text-blue-500 hover:text-rose-600">
                          <X size={13} />
                        </button>
                      </span>
                    ))}
                  </div>
                )}
              </Field>
            </div>
          </FormSection>

          {create.isError && <InlineError error={create.error} />}
        </div>
      </Drawer>

      <CountDrawer id={selectedId} onClose={() => setParams({})} />
    </Page>
  )
}

function CountDrawer({ id, onClose }: { id: string | null; onClose: () => void }) {
  const queryClient = useQueryClient()
  const perms = usePermissions()
  const [entries, setEntries] = useState<Record<string, { qty: string; reason: string }>>({})
  const count = useQuery({ queryKey: ['counts', id], queryFn: () => apiGet<StockCount>(`/api/inventory/counts/${id}`), enabled: !!id })

  function refresh(c: StockCount, msg: string) {
    toast.success(`${c.doc_number} ${msg}`)
    queryClient.invalidateQueries({ queryKey: ['counts'] })
    queryClient.invalidateQueries({ queryKey: ['inventory'] })
  }
  const start = useMutation({ mutationFn: () => apiPost<StockCount>(`/api/inventory/counts/${id}/start`), onSuccess: (c) => refresh(c, 'started — counting is blind') })
  const review = useMutation({ meta: { silent: true }, mutationFn: () => apiPost<StockCount>(`/api/inventory/counts/${id}/review`), onSuccess: (c) => refresh(c, 'sent for review') })
  const approve = useMutation({ meta: { silent: true }, mutationFn: () => apiPost<StockCount>(`/api/inventory/counts/${id}/approve`), onSuccess: (c) => refresh(c, 'approved — variances posted') })
  const close = useMutation({ mutationFn: () => apiPost<StockCount>(`/api/inventory/counts/${id}/close`), onSuccess: (c) => refresh(c, 'closed') })
  const enter = useMutation({
    mutationFn: ({ lineId, qty, reason }: { lineId: string; qty: string; reason: string }) => apiPost<StockCountLine>(`/api/inventory/counts/${id}/lines/${lineId}`, { counted_qty: qty, reason_code: reason || null }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['counts', id] }),
  })

  const c = count.data
  const approveError = approve.isError ? getApiError(approve.error) : null
  const counting = c?.status === 'COUNTING' && perms.has('stock.count.enter')
  const allCounted = !!c && (c.lines ?? []).every((l) => l.counted_qty !== null)

  return (
    <Drawer
      open={!!id}
      onClose={onClose}
      title={c?.doc_number ?? 'Stock count'}
      subtitle={c ? `${c.store?.code ?? ''} · ${c.store?.name ?? ''}` : undefined}
      width={900}
      actions={
        c ? (
          <div className="flex gap-2">
            {c.status === 'PLANNED' && perms.has('stock.count.enter') && <Button size="sm" variant="primary" disabled={start.isPending} onClick={() => start.mutate()}>Start counting</Button>}
            {c.status === 'COUNTING' && perms.has('stock.count.enter') && <Button size="sm" variant="primary" disabled={!allCounted || review.isPending} onClick={() => review.mutate()} title={allCounted ? undefined : 'Count every line first'}>Submit for review</Button>}
            {c.status === 'REVIEW' && perms.has('stock.count.post') && <Button size="sm" variant="success" disabled={approve.isPending} onClick={() => approve.mutate()}>Approve & post variances</Button>}
            {c.status === 'APPROVED' && perms.has('stock.count.post') && <Button size="sm" disabled={close.isPending} onClick={() => close.mutate()}>Close</Button>}
          </div>
        ) : null
      }
    >
      {count.isLoading && <LoadingSkeleton />}
      {count.isError && <InlineError error={count.error} />}
      {review.isError && <InlineError error={review.error} className="mb-3" />}
      {approveError && (
        <div className="mb-3">
          <InlineError error={approve.error} />
          {approveError.code === 'SECOND_APPROVER_REQUIRED' && (
            <p className="text-xs text-slate-600 mt-1 tabular">Variance value {formatMoney(String(approveError.details.variance_value ?? ''))} is above the threshold: a different user with stock.count.post must approve this count.</p>
          )}
        </div>
      )}
      {c && (
        <div className="space-y-4">
          <div className="flex items-center gap-2"><StatusBadge status={c.status} /><span className="text-xs text-slate-500">{c.status === 'COUNTING' ? 'System quantities are hidden while counting (blind count).' : ''}</span></div>
          <table className="ui-table">
            <thead><tr><th>Product</th><th>Batch</th>{c.status !== 'COUNTING' && <th className="text-right">System</th>}<th className="text-right">Counted</th>{c.status !== 'COUNTING' && <th className="text-right">Variance</th>}{c.status !== 'COUNTING' && c.lines?.some((l) => l.variance_value !== undefined) && <th className="text-right">Value</th>}<th>Reason</th>{counting && <th />}</tr></thead>
            <tbody>
              {(c.lines ?? []).map((l) => {
                const e = entries[l.id] ?? { qty: l.counted_qty !== null ? String(Number(l.counted_qty)) : '', reason: l.reason_code ?? '' }
                return (
                  <tr key={l.id}>
                    <td>{l.product?.name ?? l.product_id.slice(0, 8)}</td>
                    <td className="tabular">{l.batch?.batch_number ?? l.batch_id.slice(0, 8)}{l.batch && <div className="text-xs text-slate-500 tabular">exp {formatDate(l.batch.expiry_date)}</div>}</td>
                    {c.status !== 'COUNTING' && <td className="text-right"><QtyCell value={l.system_qty} /></td>}
                    <td className="text-right">
                      {counting ? <input value={e.qty} onChange={(ev) => setEntries({ ...entries, [l.id]: { ...e, qty: ev.target.value.replace(/[^\d.]/g, '') } })} className="ui-input h-7 w-24 tabular text-right text-sm" /> : <QtyCell value={l.counted_qty} />}
                    </td>
                    {c.status !== 'COUNTING' && <td className="text-right"><QtyCell value={l.variance_qty} className={l.variance_qty && Number(l.variance_qty) !== 0 ? 'font-bold' : ''} /></td>}
                    {c.status !== 'COUNTING' && c.lines?.some((x) => x.variance_value !== undefined) && <td className="text-right"><MoneyCell value={l.variance_value ?? null} /></td>}
                    <td>
                      {counting ? (
                        <select value={e.reason} onChange={(ev) => setEntries({ ...entries, [l.id]: { ...e, reason: ev.target.value } })} className="ui-input h-7 text-sm">
                          <option value="">None</option>
                          {(c.variance_reasons ?? []).map((r) => (<option key={r} value={r}>{titleCase(r)}</option>))}
                        </select>
                      ) : (
                        titleCase(l.reason_code) || '—'
                      )}
                    </td>
                    {counting && <td className="text-right"><Button size="sm" disabled={e.qty === '' || enter.isPending} onClick={() => enter.mutate({ lineId: l.id, qty: e.qty, reason: e.reason })}>{l.counted_qty !== null ? 'Update' : 'Save'}</Button></td>}
                  </tr>
                )
              })}
            </tbody>
          </table>
          {counting && <p className="text-xs text-slate-500">A variance without a reason code cannot go to review. Reasons: {(c.variance_reasons ?? []).map(titleCase).join(', ')}.</p>}
        </div>
      )}
    </Drawer>
  )
}
