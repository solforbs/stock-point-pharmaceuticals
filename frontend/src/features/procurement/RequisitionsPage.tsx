import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Calendar, Package, Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { ProductSearch } from '../../components/ProductSearch'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { ConfirmDialog, Modal } from '../../components/ui/Modal'
import { QtyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, DrawerFooter, Field, FormSection, Input, PrimaryAction, Select, Textarea } from '../../components/ui/primitives'
import { apiGet, apiPost } from '../../lib/api'
import { formatDate, formatDateTime, titleCase } from '../../lib/format'
import { useSuppliers, useUoms } from '../../lib/hooks'
import { formatQty } from '../../lib/money'
import { usePermissions } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Paginated, Product, PurchaseOrder, ReorderSuggestion, Requisition } from '../../lib/types'
import { ExpiryBadge } from '../inventory/StockOnHandPage'

const STATUSES = ['DRAFT', 'PENDING_APPROVAL', 'APPROVED', 'REJECTED', 'CONVERTED']
type ReqLine = { key: string; product: Product; qty_base: string; notes: string }

/** Part 9.1 — requisition → approval → supplier selection → purchase order; plus the reorder advisor (Part 22.1). */
export default function RequisitionsPage() {
  const [params, setParams] = useSearchParams()
  const queryClient = useQueryClient()
  const perms = usePermissions()
  const [tab, setTab] = useState<'requisitions' | 'reorder'>(params.get('tab') === 'reorder' ? 'reorder' : 'requisitions')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const [neededBy, setNeededBy] = useState('')
  const [notes, setNotes] = useState('')
  const [lines, setLines] = useState<ReqLine[]>([])
  const selectedId = params.get('requisition')

  const list = useQuery({
    queryKey: ['requisitions', 'list', status, page],
    queryFn: () => apiGet<Paginated<Requisition>>('/api/requisitions', { status, page, per_page: 50 }),
    placeholderData: (prev) => prev,
    enabled: tab === 'requisitions',
  })

  const create = useMutation({
    meta: { silent: true },
    mutationFn: (payload: { needed_by: string | null; notes: string | null; lines: { product_id: string; qty_base: string; notes: string | null }[] }) => apiPost<Requisition>('/api/requisitions', payload),
    onSuccess: (r) => {
      toast.success(`Requisition ${r.doc_number} drafted`)
      queryClient.invalidateQueries({ queryKey: ['requisitions'] })
      setCreating(false)
      setLines([])
      setTab('requisitions')
      setParams({ requisition: r.id })
    },
  })

  const columns: Column<Requisition>[] = [
    { key: 'doc', header: 'Document', render: (r) => <span className="font-semibold tabular">{r.doc_number}</span>, sortValue: (r) => r.doc_number },
    { key: 'status', header: 'Status', render: (r) => <StatusBadge status={r.status} /> },
    { key: 'lines', header: 'Lines', align: 'right', render: (r) => <span className="tabular">{r.lines_count ?? '—'}</span> },
    { key: 'needed', header: 'Needed by', render: (r) => formatDate(r.needed_by), sortValue: (r) => r.needed_by ?? '' },
    { key: 'created', header: 'Created', render: (r) => formatDateTime(r.created_at), sortValue: (r) => r.created_at ?? '' },
    { key: 'notes', header: 'Notes', render: (r) => r.notes ?? '—' },
  ]

  return (
    <Page>
      <PageHeader
        parent="Procurement & Supply"
        title="Purchase Requisitions"
        subtitle="Departmental medicine demands, approvals workflow, and conversion to supplier purchase orders."
        actions={
          perms.has('requisition.create') ? (
            <PrimaryAction icon={Plus} onClick={() => setCreating(true)}>
              New requisition
            </PrimaryAction>
          ) : null
        }
      />
      <div className="flex items-center gap-1.5 p-1 bg-slate-100/80 rounded-xl border border-slate-200/60 w-fit mb-4">
        {(['requisitions', 'reorder'] as const).map((t) => (
          <button
            key={t}
            type="button"
            onClick={() => setTab(t)}
            className={`h-8 px-4 rounded-lg text-xs font-bold transition-all ${tab === t ? 'bg-white text-slate-900 shadow-xs border border-slate-200/80' : 'text-slate-600 hover:text-slate-900'}`}
          >
            {t === 'requisitions' ? 'Requisitions' : 'Reorder suggestions'}
          </button>
        ))}
      </div>

      {tab === 'requisitions' ? (
        <>
          <FilterBar>
            <Field label="Status">
              <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
                <option value="">All</option>
                {STATUSES.map((s) => (<option key={s} value={s}>{titleCase(s)}</option>))}
              </Select>
            </Field>
          </FilterBar>
          <div className="ui-card">
            <DataTable columns={columns} rows={list.data?.data} rowKey={(r) => r.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(r) => setParams({ requisition: r.id })} selectedKey={selectedId} emptyTitle="No requisitions" />
            <Pagination page={list.data} onPage={setPage} />
          </div>
        </>
      ) : (
        <ReorderSuggestions canCreate={perms.has('requisition.create')} isCreating={create.isPending} onCreate={(rows) => create.mutate({ needed_by: rows.reduce<string | null>((min, r) => (min === null || r.required_by < min ? r.required_by : min), null), notes: 'From reorder suggestions', lines: rows.map((r) => ({ product_id: r.product_id, qty_base: r.suggested_qty_base, notes: r.formula })) })} />
      )}

      <Drawer
        open={creating}
        onClose={() => setCreating(false)}
        title="New Purchase Requisition"
        subtitle="Draft demand items for internal review and management approval"
        width={780}
        footer={
          <DrawerFooter
            badge={`${lines.length} ${lines.length === 1 ? 'item' : 'items'}`}
            onCancel={() => setCreating(false)}
            onSubmit={() => create.mutate({ needed_by: neededBy || null, notes: notes || null, lines: lines.map((l) => ({ product_id: l.product.id, qty_base: l.qty_base, notes: l.notes || null })) })}
            submitLabel="Create requisition"
            disabled={lines.length === 0 || lines.some((l) => !(Number(l.qty_base) > 0)) || create.isPending}
            isPending={create.isPending}
          />
        }
      >
        <div className="space-y-4">
          <FormSection
            title="Requisition Schedule"
            description="Set required fulfillment date and operational notes"
            icon={Calendar}
          >
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
              <Field label="Needed By Date">
                <Input type="date" value={neededBy} onChange={(e) => setNeededBy(e.target.value)} />
              </Field>
              <Field label="Justification / Notes">
                <Textarea rows={1} value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="e.g. Weekly buffer restock" />
              </Field>
            </div>
          </FormSection>

          <FormSection
            title="Required Medication Lines"
            description="Search and add products with required base unit quantities"
            icon={Package}
            badge={`${lines.length} items`}
          >
            <div className="space-y-3">
              <ProductSearch onSelect={(p) => setLines([...lines, { key: `${p.id}-${Date.now()}`, product: p, qty_base: '', notes: '' }])} />
              {lines.length > 0 && (
                <div className="overflow-x-auto rounded-xl border border-slate-200">
                  <table className="ui-table">
                    <thead><tr><th>Product</th><th>Qty (base)</th><th>Notes</th><th /></tr></thead>
                    <tbody>
                      {lines.map((l) => (
                        <tr key={l.key}>
                          <td><div className="font-semibold text-slate-900">{l.product.name}</div><div className="text-xs text-slate-500 font-mono font-medium">{l.product.code} · {l.product.base_uom?.code}</div></td>
                          <td><input value={l.qty_base} onChange={(e) => setLines(lines.map((x) => (x.key === l.key ? { ...x, qty_base: e.target.value.replace(/[^\d.]/g, '') } : x)))} className="ui-input h-7 w-24 tabular text-right font-bold" /></td>
                          <td><input value={l.notes} onChange={(e) => setLines(lines.map((x) => (x.key === l.key ? { ...x, notes: e.target.value } : x)))} className="ui-input h-7" placeholder="Line notes..." /></td>
                          <td className="text-right"><Button size="sm" variant="ghost" onClick={() => setLines(lines.filter((x) => x.key !== l.key))} aria-label="Remove"><Trash2 size={13} /></Button></td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}
            </div>
          </FormSection>

          {create.isError && <InlineError error={create.error} />}
        </div>
      </Drawer>

      <RequisitionDrawer id={selectedId} onClose={() => setParams({})} />
    </Page>
  )
}

function ReorderSuggestions({ canCreate, isCreating, onCreate }: { canCreate: boolean; isCreating: boolean; onCreate: (rows: ReorderSuggestion[]) => void }) {
  const [selected, setSelected] = useState<Set<string>>(new Set())
  const suggestions = useQuery({ queryKey: ['reorder-suggestions'], queryFn: () => apiGet<{ data: ReorderSuggestion[]; formula: string; generated_at: string }>('/api/procurement/reorder-suggestions') })
  const rows = suggestions.data?.data ?? []
  const groups = new Map<string, ReorderSuggestion[]>()
  for (const r of rows) {
    const key = r.supplier_name ?? 'No supplier on record'
    groups.set(key, [...(groups.get(key) ?? []), r])
  }
  function toggle(id: string) {
    setSelected((prev) => { const next = new Set(prev); if (next.has(id)) next.delete(id); else next.add(id); return next })
  }
  const chosen = rows.filter((r) => selected.has(r.product_id))

  return (
    <div className="space-y-3">
      <div className="flex items-center gap-3 text-xs text-slate-500">
        <span className="flex-1">{suggestions.data?.formula}</span>
        {canCreate && <Button variant="primary" size="sm" disabled={chosen.length === 0 || isCreating} onClick={() => onCreate(chosen)}>{isCreating ? 'Creating…' : `Create requisition from ${chosen.length} selected`}</Button>}
      </div>
      {suggestions.isLoading && <LoadingSkeleton />}
      {suggestions.isError && <InlineError error={suggestions.error} />}
      {suggestions.data && rows.length === 0 && <div className="ui-card p-6 text-sm text-slate-500">Every product has enough cover. Nothing to reorder.</div>}
      {[...groups.entries()].map(([supplier, list]) => (
        <div key={supplier} className="ui-card">
          <header className="px-4 py-2 border-b border-slate-200 flex items-center gap-2 text-sm font-bold text-slate-900">
            <input type="checkbox" checked={list.every((r) => selected.has(r.product_id))} onChange={(e) => setSelected((prev) => { const next = new Set(prev); list.forEach((r) => (e.target.checked ? next.add(r.product_id) : next.delete(r.product_id))); return next })} />
            {supplier} <span className="text-slate-500 font-normal">· {list.length} product{list.length === 1 ? '' : 's'}</span>
          </header>
          <table className="ui-table">
            <thead><tr><th /><th>Product</th><th className="text-right">Reorder point</th><th className="text-right">Free to sell</th><th className="text-right">On order</th><th className="text-right">Suggested</th><th>Required by</th><th>Nearest expiry</th></tr></thead>
            <tbody>
              {list.map((r) => (
                <tr key={r.product_id} className="is-clickable" onClick={() => toggle(r.product_id)}>
                  <td><input type="checkbox" checked={selected.has(r.product_id)} onChange={() => toggle(r.product_id)} onClick={(e) => e.stopPropagation()} /></td>
                  <td><div className="font-semibold">{r.product_name}</div><div className="text-xs text-slate-500 font-mono">{r.product_code} · {r.base_uom ?? ''} · lead {r.lead_time_days} d</div></td>
                  <td className="text-right"><QtyCell value={r.reorder_point} /></td>
                  <td className="text-right"><QtyCell value={r.free_to_sell} className={Number(r.free_to_sell) < Number(r.reorder_point) ? 'text-rose-600 font-bold' : ''} /></td>
                  <td className="text-right"><QtyCell value={r.on_order} /></td>
                  <td className="text-right font-bold tabular">{formatQty(r.suggested_qty_base)}</td>
                  <td className="tabular">{formatDate(r.required_by)}</td>
                  <td><ExpiryBadge date={r.nearest_expiry} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ))}
    </div>
  )
}

type ConvertLine = { requisition_line_id: string; product_name: string; base_qty: string; uom_id: string; qty_ordered: string; unit_price: string }

function RequisitionDrawer({ id, onClose }: { id: string | null; onClose: () => void }) {
  const queryClient = useQueryClient()
  const perms = usePermissions()
  const suppliers = useSuppliers()
  const uoms = useUoms()
  const [rejecting, setRejecting] = useState(false)
  const [converting, setConverting] = useState(false)
  const [supplierId, setSupplierId] = useState('')
  const [expectedDate, setExpectedDate] = useState('')
  const [convertLines, setConvertLines] = useState<ConvertLine[]>([])
  const [po, setPo] = useState<PurchaseOrder | null>(null)
  const req = useQuery({ queryKey: ['requisitions', id], queryFn: () => apiGet<Requisition>(`/api/requisitions/${id}`), enabled: !!id })

  function refresh(r: Requisition, msg: string) {
    toast.success(`${r.doc_number} ${msg}`)
    queryClient.invalidateQueries({ queryKey: ['requisitions'] })
  }
  const submit = useMutation({ mutationFn: () => apiPost<Requisition>(`/api/requisitions/${id}/submit`), onSuccess: (r) => refresh(r, 'submitted for approval') })
  const approve = useMutation({ mutationFn: () => apiPost<Requisition>(`/api/requisitions/${id}/approve`), onSuccess: (r) => refresh(r, 'approved') })
  const reject = useMutation({ mutationFn: (reason: string) => apiPost<Requisition>(`/api/requisitions/${id}/reject`, { reason }), onSuccess: (r) => { setRejecting(false); refresh(r, 'rejected') } })
  const convert = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<PurchaseOrder>(`/api/requisitions/${id}/convert`, { supplier_id: supplierId, expected_date: expectedDate || null, lines: convertLines.map((l) => ({ requisition_line_id: l.requisition_line_id, uom_id: l.uom_id, qty_ordered: l.qty_ordered, unit_price: l.unit_price })) }),
    onSuccess: (created) => {
      setPo(created)
      setConverting(false)
      toast.success(`Purchase order ${created.doc_number} created`, 'It still needs its own approval.')
      queryClient.invalidateQueries({ queryKey: ['requisitions'] })
      queryClient.invalidateQueries({ queryKey: ['purchase-orders'] })
    },
  })

  const r = req.data
  function openConvert() {
    setConvertLines((r?.lines ?? []).map((l) => ({ requisition_line_id: l.id, product_name: l.product?.name ?? l.product_id.slice(0, 8), base_qty: l.qty_requested, uom_id: l.product?.base_uom_id ?? '', qty_ordered: String(Number(l.qty_requested)), unit_price: '' })))
    setConverting(true)
  }
  const convertValid = !!supplierId && convertLines.length > 0 && convertLines.every((l) => l.uom_id && Number(l.qty_ordered) > 0 && /^\d+(\.\d+)?$/.test(l.unit_price))

  return (
    <Drawer
      open={!!id}
      onClose={onClose}
      title={r?.doc_number ?? 'Requisition'}
      subtitle={r ? `Needed by ${formatDate(r.needed_by)}` : undefined}
      width={760}
      actions={
        r ? (
          <div className="flex gap-2">
            {r.status === 'DRAFT' && perms.has('requisition.create') && <Button size="sm" variant="primary" disabled={submit.isPending} onClick={() => submit.mutate()}>Submit</Button>}
            {r.status === 'PENDING_APPROVAL' && perms.has('requisition.approve') && <><Button size="sm" variant="success" disabled={approve.isPending} onClick={() => approve.mutate()}>Approve</Button><Button size="sm" variant="danger" onClick={() => setRejecting(true)}>Reject</Button></>}
            {r.status === 'APPROVED' && perms.has('po.create') && <Button size="sm" variant="primary" onClick={openConvert}>Convert to PO</Button>}
          </div>
        ) : null
      }
    >
      {req.isLoading && <LoadingSkeleton />}
      {req.isError && <InlineError error={req.error} />}
      {r && (
        <div className="space-y-4">
          <div className="flex items-center gap-2"><StatusBadge status={r.status} />{po && <Link to={`/buy/purchase-orders?po=${po.id}`} className="text-xs text-blue-600 hover:text-blue-700 hover:underline font-semibold">Open PO {po.doc_number}</Link>}</div>
          <DescriptionList items={[{ label: 'Notes', value: r.notes ?? '—' }, { label: 'Created', value: formatDateTime(r.created_at) }]} />
          <table className="ui-table">
            <thead><tr><th>Product</th><th className="text-right">Qty (base)</th><th>Notes</th></tr></thead>
            <tbody>
              {(r.lines ?? []).map((l) => (
                <tr key={l.id}><td>{l.product?.name ?? l.product_id.slice(0, 8)}</td><td className="text-right"><QtyCell value={l.qty_requested} unit={l.product?.base_uom?.code} /></td><td>{l.notes ?? '—'}</td></tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      <ConfirmDialog open={rejecting} title="Reject requisition" confirmLabel="Reject" danger requireReason="Reason" isPending={reject.isPending} onCancel={() => setRejecting(false)} onConfirm={(reason) => reject.mutate(reason)} />
      <Modal
        open={converting}
        onClose={() => setConverting(false)}
        title="Convert to purchase order"
        width={760}
        footer={<><Button onClick={() => setConverting(false)}>Cancel</Button><Button variant="primary" disabled={!convertValid || convert.isPending} onClick={() => convert.mutate()}>{convert.isPending ? 'Creating…' : 'Create purchase order'}</Button></>}
      >
        <div className="space-y-3">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <Field label="Supplier" required>
              <Select value={supplierId} onChange={(e) => setSupplierId(e.target.value)}>
                <option value="">Choose…</option>
                {(suppliers.data?.data ?? []).map((s) => (<option key={s.id} value={s.id} disabled={s.status !== 'ACTIVE'}>{s.name}{s.status !== 'ACTIVE' ? ` (${s.status})` : ''}</option>))}
              </Select>
            </Field>
            <Field label="Expected date"><Input type="date" value={expectedDate} onChange={(e) => setExpectedDate(e.target.value)} /></Field>
          </div>
          <table className="ui-table">
            <thead><tr><th>Product</th><th className="text-right">Requested (base)</th><th>Purchase UOM</th><th className="text-right">Qty ordered</th><th className="text-right">Unit price</th></tr></thead>
            <tbody>
              {convertLines.map((l, i) => (
                <tr key={l.requisition_line_id}>
                  <td>{l.product_name}</td>
                  <td className="text-right"><QtyCell value={l.base_qty} /></td>
                  <td>
                    <select value={l.uom_id} onChange={(e) => setConvertLines(convertLines.map((x, j) => (j === i ? { ...x, uom_id: e.target.value } : x)))} className="ui-input h-7">
                      <option value="">UOM…</option>
                      {(uoms.data ?? []).map((u) => (<option key={u.id} value={u.id}>{u.code}</option>))}
                    </select>
                  </td>
                  <td><input value={l.qty_ordered} onChange={(e) => setConvertLines(convertLines.map((x, j) => (j === i ? { ...x, qty_ordered: e.target.value.replace(/[^\d.]/g, '') } : x)))} className="ui-input h-7 w-20 tabular text-right" /></td>
                  <td><input value={l.unit_price} onChange={(e) => setConvertLines(convertLines.map((x, j) => (j === i ? { ...x, unit_price: e.target.value.replace(/[^\d.]/g, '') } : x)))} className="ui-input h-7 w-24 tabular text-right" /></td>
                </tr>
              ))}
            </tbody>
          </table>
          <p className="text-xs text-slate-500">The UOM must be a purchase unit for the product; a selling-only unit is refused (INVALID_INPUT).</p>
          {convert.isError && <InlineError error={convert.error} />}
        </div>
      </Modal>
    </Drawer>
  )
}
