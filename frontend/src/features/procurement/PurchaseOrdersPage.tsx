import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Trash2 } from 'lucide-react'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { ProductSearch } from '../../components/ProductSearch'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { MoneyCell, QtyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, Field, Input, Select } from '../../components/ui/primitives'
import { apiGet, apiPost } from '../../lib/api'
import { formatDate, formatDateTime } from '../../lib/format'
import { usePurchaseOrder, useSuppliers } from '../../lib/hooks'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Paginated, Product, PurchaseOrder } from '../../lib/types'
import { ExpiryBadge } from '../inventory/StockOnHandPage'

const STATUSES = ['DRAFT', 'PENDING_APPROVAL', 'APPROVED', 'SENT', 'PARTIALLY_RECEIVED', 'RECEIVED', 'CLOSED', 'CANCELLED']

type PoLine = { key: string; product: Product; uom_id: string; qty_ordered: string; unit_price: string }

export default function PurchaseOrdersPage() {
  const [params, setParams] = useSearchParams()
  const queryClient = useQueryClient()
  const canCreate = usePermission('po.create')
  const suppliers = useSuppliers()
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(!!params.get('supplier'))
  const [supplierId, setSupplierId] = useState(params.get('supplier') ?? '')
  const [expectedDate, setExpectedDate] = useState('')
  const [lines, setLines] = useState<PoLine[]>([])
  const selectedId = params.get('po')

  const list = useQuery({
    queryKey: ['purchase-orders', 'list', status, page],
    queryFn: () => apiGet<Paginated<PurchaseOrder>>('/api/purchase-orders', { status, page, per_page: 50 }),
    placeholderData: (prev) => prev,
  })

  const create = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<PurchaseOrder>('/api/purchase-orders', { supplier_id: supplierId, expected_date: expectedDate || null, lines: lines.map((l) => ({ product_id: l.product.id, uom_id: l.uom_id, qty_ordered: l.qty_ordered, unit_price: l.unit_price })) }),
    onSuccess: (po) => {
      toast.success(`Purchase order ${po.doc_number} created`)
      queryClient.invalidateQueries({ queryKey: ['purchase-orders'] })
      setCreating(false)
      setLines([])
      setParams({ po: po.id })
    },
  })

  const columns: Column<PurchaseOrder>[] = [
    { key: 'doc', header: 'Document', render: (po) => <span className="font-semibold tabular">{po.doc_number}</span>, sortValue: (po) => po.doc_number },
    { key: 'supplier', header: 'Supplier', render: (po) => po.supplier?.name ?? '—', sortValue: (po) => po.supplier?.name ?? '' },
    { key: 'status', header: 'Status', render: (po) => <StatusBadge status={po.status} /> },
    { key: 'lines', header: 'Lines', align: 'right', render: (po) => <span className="tabular">{po.lines_count ?? '—'}</span> },
    { key: 'expected', header: 'Expected', render: (po) => formatDate(po.expected_date), sortValue: (po) => po.expected_date ?? '' },
    { key: 'created', header: 'Created', render: (po) => formatDateTime(po.created_at), sortValue: (po) => po.created_at ?? '' },
  ]
  const validLines = lines.length > 0 && lines.every((l) => l.uom_id && Number(l.qty_ordered) > 0 && /^\d+(\.\d+)?$/.test(l.unit_price))

  return (
    <Page>
      <PageHeader parent="Buy" title="Purchase Orders" subtitle="DRAFT → APPROVED → SENT. Receiving happens in Goods Receipts." actions={canCreate ? <Button variant="primary" onClick={() => setCreating(true)}>New purchase order</Button> : null} />
      <FilterBar>
        <Field label="Status"><Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}><option value="">All</option>{STATUSES.map((s) => (<option key={s} value={s}>{s}</option>))}</Select></Field>
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(po) => po.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(po) => setParams({ po: po.id })} selectedKey={selectedId} emptyTitle="No purchase orders" />
        <Pagination page={list.data} onPage={setPage} />
      </div>

      <Drawer open={creating} onClose={() => setCreating(false)} title="New purchase order" width={820}>
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-3">
            <Field label="Supplier" required>
              <Select value={supplierId} onChange={(e) => setSupplierId(e.target.value)}>
                <option value="">Choose…</option>
                {(suppliers.data?.data ?? []).map((s) => (<option key={s.id} value={s.id} disabled={s.status !== 'ACTIVE' || !s.is_active}>{s.name}{s.status !== 'ACTIVE' ? ` (${s.status})` : ''}</option>))}
              </Select>
            </Field>
            <Field label="Expected date"><Input type="date" value={expectedDate} onChange={(e) => setExpectedDate(e.target.value)} /></Field>
          </div>
          <Field label="Lines" required hint="Unit price is per purchase UOM.">
            <div className="space-y-2">
              <ProductSearch onSelect={(p) => {
                const uoms = (p.uoms ?? []).filter((u) => u.is_purchase || u.is_base)
                const uom = uoms.find((u) => u.is_purchase && !u.is_base) ?? uoms[0]
                setLines([...lines, { key: `${p.id}-${Date.now()}`, product: p, uom_id: uom?.uom_id ?? '', qty_ordered: '1', unit_price: '' }])
              }} />
              {lines.length > 0 && (
                <table className="ui-table">
                  <thead><tr><th>Product</th><th>UOM</th><th className="text-right">Qty</th><th className="text-right">Unit price</th><th /></tr></thead>
                  <tbody>
                    {lines.map((l) => (
                      <tr key={l.key}>
                        <td><div className="font-semibold">{l.product.name}</div><div className="text-[10.5px] text-[var(--text-muted)]">{l.product.code}</div></td>
                        <td>
                          <select value={l.uom_id} onChange={(e) => setLines(lines.map((x) => (x.key === l.key ? { ...x, uom_id: e.target.value } : x)))} className="ui-input h-7 w-auto">
                            {(l.product.uoms ?? []).filter((u) => u.is_purchase || u.is_base).map((u) => (<option key={u.uom_id} value={u.uom_id}>{u.uom?.code} {u.factor_to_base !== 1 ? `(×${u.factor_to_base})` : ''}</option>))}
                          </select>
                        </td>
                        <td><input type="text" inputMode="decimal" value={l.qty_ordered} onChange={(e) => setLines(lines.map((x) => (x.key === l.key ? { ...x, qty_ordered: e.target.value.replace(/[^\d.]/g, '') } : x)))} className="ui-input h-7 w-20 tabular text-right" /></td>
                        <td><input type="text" inputMode="decimal" value={l.unit_price} onChange={(e) => setLines(lines.map((x) => (x.key === l.key ? { ...x, unit_price: e.target.value.replace(/[^\d.]/g, '') } : x)))} className="ui-input h-7 w-28 tabular text-right" /></td>
                        <td className="text-right"><Button size="sm" variant="ghost" onClick={() => setLines(lines.filter((x) => x.key !== l.key))} aria-label="Remove"><Trash2 size={13} /></Button></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </div>
          </Field>
          {create.isError && <InlineError error={create.error} />}
          <div className="flex justify-end gap-2">
            <Button onClick={() => setCreating(false)}>Cancel</Button>
            <Button variant="primary" disabled={!supplierId || !validLines || create.isPending} onClick={() => create.mutate()}>{create.isPending ? 'Saving…' : 'Create purchase order'}</Button>
          </div>
        </div>
      </Drawer>

      <PurchaseOrderDrawer id={selectedId} onClose={() => setParams({})} />
    </Page>
  )
}

function PurchaseOrderDrawer({ id, onClose }: { id: string | null; onClose: () => void }) {
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
      title={p?.doc_number ?? 'Purchase order'}
      subtitle={p ? `${p.supplier?.name ?? ''} · ${formatDateTime(p.created_at)}` : undefined}
      width={760}
      actions={
        p ? (
          <div className="flex gap-2">
            {canApprove && (p.status === 'DRAFT' || p.status === 'PENDING_APPROVAL') && <Button size="sm" variant="success" disabled={act.isPending} onClick={() => act.mutate('approve')}>Approve</Button>}
            {canSend && p.status === 'APPROVED' && <Button size="sm" variant="primary" disabled={act.isPending} onClick={() => act.mutate('send')}>Send to supplier</Button>}
            {['SENT', 'APPROVED', 'PARTIALLY_RECEIVED'].includes(p.status) && <Link to={`/buy/goods-receipts?po=${p.id}`} className="inline-flex items-center h-7 px-2.5 rounded-md border border-[var(--border-strong)] text-[11.5px] font-semibold">Receive</Link>}
          </div>
        ) : null
      }
    >
      {po.isLoading && <LoadingSkeleton />}
      {po.isError && <InlineError error={po.error} />}
      {p && (
        <div className="space-y-4">
          <div className="flex items-center gap-2"><StatusBadge status={p.status} />{p.supplier?.licence_expiry && <span className="text-[11.5px] text-[var(--text-muted)] flex items-center gap-1">Supplier licence <ExpiryBadge date={p.supplier.licence_expiry} /></span>}</div>
          <DescriptionList items={[{ label: 'Expected', value: formatDate(p.expected_date) }, { label: 'Sent', value: formatDateTime(p.sent_at) }, { label: 'Order value', value: <MoneyCell value={total.toFixed(4)} symbol /> }]} />
          <table className="ui-table">
            <thead><tr><th>Product</th><th>UOM</th><th className="text-right">Qty</th><th className="text-right">Unit price</th><th className="text-right">Line total</th></tr></thead>
            <tbody>
              {(p.lines ?? []).map((l) => (
                <tr key={l.id}>
                  <td><div className="font-semibold">{l.product?.name ?? l.product_id.slice(0, 8)}</div><div className="text-[10.5px] text-[var(--text-muted)]">{l.product?.code}</div></td>
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
                  <li key={g.id} className="flex items-center gap-2"><Link to={`/buy/goods-receipts?receipt=${g.id}`} className="tabular font-semibold text-[var(--color-navy)] underline">{g.doc_number}</Link><StatusBadge status={g.status} /><span className="text-[var(--text-muted)]">{formatDateTime(g.received_at)}</span></li>
                ))}
              </ul>
            </div>
          )}
        </div>
      )}
    </Drawer>
  )
}
