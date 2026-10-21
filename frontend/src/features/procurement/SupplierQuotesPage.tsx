import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { AlertTriangle, Award, Calendar, CheckCircle2, Package, Plus, Scale, Trash2, Users } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { PdfDownloadButton } from '../../components/PdfDownloadButton'
import { ProductSearch } from '../../components/ProductSearch'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { ConfirmDialog, Modal } from '../../components/ui/Modal'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, DrawerFooter, Field, FormSection, Input, PrimaryAction, Select, Textarea } from '../../components/ui/primitives'
import { apiGet, apiPatch, apiPost, apiPut } from '../../lib/api'
import { formatDate, formatDateTime, titleCase } from '../../lib/format'
import { useSuppliers, useUoms } from '../../lib/hooks'
import { formatKes, formatQty } from '../../lib/money'
import { usePermissions } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { CbaAnalysis, CbaRisk, Paginated, Product, Rfq, RfqInvite } from '../../lib/types'

const STATUSES = ['DRAFT', 'SENT', 'CLOSED', 'AWARDED', 'CANCELLED']

type DraftLine = { key: string; product: Product; uom_id: string; qty: string; notes: string; factor: string }

/** A unit the product is not set up with yet (other than its base unit) needs its pack size. */
const needsFactor = (l: DraftLine) => !!l.uom_id && l.uom_id !== l.product.base_uom_id && !(l.product.uoms ?? []).some((u) => u.uom_id === l.uom_id)

/** Client item 19 — requests for quotation, supplier quotes, the competitive bid analysis and the award. */
export default function SupplierQuotesPage() {
  const [params, setParams] = useSearchParams()
  const perms = usePermissions()
  const [status, setStatus] = useState('')
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const selectedId = params.get('rfq')

  const list = useQuery({
    queryKey: ['rfqs', 'list', status, q, page],
    queryFn: () => apiGet<Paginated<Rfq>>('/api/rfqs', { status, q, page, per_page: 50 }),
    placeholderData: (prev) => prev,
  })

  const columns: Column<Rfq>[] = [
    { key: 'doc', header: 'Request', render: (r) => <span className="font-semibold tabular">{r.doc_number}</span>, sortValue: (r) => r.doc_number },
    { key: 'title', header: 'Title', render: (r) => r.title },
    { key: 'status', header: 'Status', render: (r) => <StatusBadge status={r.status} /> },
    { key: 'lines', header: 'Lines', align: 'right', render: (r) => <span className="tabular">{r.lines_count ?? '—'}</span> },
    { key: 'quotes', header: 'Quotes', align: 'right', render: (r) => <span className="tabular">{r.quotes_received_count ?? 0} of {r.suppliers_count ?? 0}</span> },
    { key: 'needed', header: 'Needed by', render: (r) => formatDate(r.needed_by), sortValue: (r) => r.needed_by ?? '' },
    { key: 'created', header: 'Created', render: (r) => formatDateTime(r.created_at), sortValue: (r) => r.created_at ?? '' },
  ]

  return (
    <Page>
      <PageHeader
        parent="Procurement"
        title="Supplier Quotes & CBA"
        subtitle="Ask suppliers for prices, compare their quotes side by side and award with the reasons on record."
        actions={perms.has('rfq.manage') ? <PrimaryAction icon={Plus} onClick={() => setCreating(true)}>New request</PrimaryAction> : null}
      />
      <FilterBar>
        <div className="w-full sm:w-56">
          <Field label="Status">
            <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
              <option value="">All</option>
              {STATUSES.map((s) => (<option key={s} value={s}>{titleCase(s)}</option>))}
            </Select>
          </Field>
        </div>
        <div className="w-full sm:w-72">
          <Field label="Search">
            <Input value={q} onChange={(e) => { setQ(e.target.value); setPage(1) }} placeholder="Title or number" />
          </Field>
        </div>
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(r) => r.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(r) => setParams({ rfq: r.id })} selectedKey={selectedId} emptyTitle="No requests for quotation yet" emptyHint="Start one with New request, invite suppliers, then enter their quotes." />
        <Pagination page={list.data} onPage={setPage} />
      </div>

      <NewRfqDrawer open={creating} onClose={() => setCreating(false)} onCreated={(r) => { setCreating(false); setParams({ rfq: r.id }) }} />
      <RfqDrawer id={selectedId} onClose={() => setParams({})} />
    </Page>
  )
}

function NewRfqDrawer({ open, onClose, onCreated }: { open: boolean; onClose: () => void; onCreated: (r: Rfq) => void }) {
  const queryClient = useQueryClient()
  const suppliers = useSuppliers()
  const allUoms = useUoms()
  const [title, setTitle] = useState('')
  const [neededBy, setNeededBy] = useState('')
  const [notes, setNotes] = useState('')
  const [lines, setLines] = useState<DraftLine[]>([])
  const [supplierIds, setSupplierIds] = useState<string[]>([])
  const [assignments, setAssignments] = useState<Assignments>({})

  const create = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<Rfq>('/api/rfqs', {
      title, needed_by: neededBy || null, notes: notes || null,
      lines: lines.map((l) => ({ product_id: l.product.id, uom_id: l.uom_id, qty: l.qty, notes: l.notes || null, ...(needsFactor(l) ? { factor_to_base: Number(l.factor) } : {}) })),
      supplier_products: assignmentPayload(assignments, supplierIds),
      supplier_ids: supplierIds,
    }),
    onSuccess: (r) => {
      toast.success(`Request ${r.doc_number} drafted`, 'Send it when the list is ready.')
      queryClient.invalidateQueries({ queryKey: ['rfqs'] })
      setTitle(''); setNeededBy(''); setNotes(''); setLines([]); setSupplierIds([])
      onCreated(r)
    },
  })

  const usable = (suppliers.data?.data ?? []).filter((s) => s.status === 'ACTIVE' && s.is_active)
  const valid = title.trim() !== '' && lines.length > 0 && lines.every((l) => l.uom_id && Number(l.qty) > 0 && (!needsFactor(l) || (/^\d+$/.test(l.factor) && Number(l.factor) >= 2))) && supplierIds.length > 0 && matrixValid(assignments, supplierIds)

  return (
    <Drawer
      open={open}
      onClose={onClose}
      title="New request for quotation"
      subtitle="List what you need and choose the suppliers to ask"
      width={840}
      footer={<DrawerFooter badge={`${lines.length} lines · ${supplierIds.length} suppliers`} onCancel={onClose} onSubmit={() => create.mutate()} submitLabel="Create request" disabled={!valid} isPending={create.isPending} />}
    >
      <div className="space-y-4">
        <FormSection title="Request" description="A short title, when the goods are needed and any notes for suppliers" icon={Calendar}>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <Field label="Title" required><Input value={title} onChange={(e) => setTitle(e.target.value)} placeholder="e.g. Antibiotics restock, October" /></Field>
            <Field label="Needed by"><Input type="date" value={neededBy} onChange={(e) => setNeededBy(e.target.value)} /></Field>
          </div>
          <Field label="Notes for suppliers"><Textarea rows={2} value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="e.g. Minimum 18 months shelf life" /></Field>
        </FormSection>

        <FormSection title="Items" description="Products, purchase unit and quantity" icon={Package} badge={`${lines.length} items`}>
          <ProductSearch onSelect={(p) => {
            const uoms = (p.uoms ?? []).filter((u) => u.is_purchase)
            const uom = uoms.find((u) => !u.is_base) ?? uoms[0]
            setLines([...lines, { key: `${p.id}-${Date.now()}`, product: p, uom_id: uom?.uom_id ?? p.base_uom_id ?? '', qty: '1', notes: '', factor: '' }])
          }} />
          {lines.length > 0 && (
            <div className="overflow-x-auto rounded-xl border border-slate-200">
              <table className="ui-table">
                <thead><tr><th>Product</th><th>Unit</th><th className="text-right">Qty</th><th>Notes</th><th /></tr></thead>
                <tbody>
                  {lines.map((l) => (
                    <tr key={l.key}>
                      <td><div className="font-semibold text-slate-900">{l.product.name}</div><div className="text-xs text-slate-500 font-mono">{l.product.code}</div></td>
                      <td>
                        <select value={l.uom_id} onChange={(e) => setLines(lines.map((x) => (x.key === l.key ? { ...x, uom_id: e.target.value } : x)))} className="ui-input h-7 w-auto">
                          <option value="">Unit…</option>
                          <optgroup label="This product's units">
                            {(l.product.uoms ?? []).map((u) => (<option key={u.uom_id} value={u.uom_id}>{u.uom?.code} {u.factor_to_base !== 1 ? `(×${u.factor_to_base})` : ''}</option>))}
                            {!(l.product.uoms ?? []).some((u) => u.uom_id === l.product.base_uom_id) && l.product.base_uom_id && (
                              <option value={l.product.base_uom_id}>{l.product.base_uom?.code ?? 'Base unit'}</option>
                            )}
                          </optgroup>
                          <optgroup label="Other units (give the pack size)">
                            {(allUoms.data ?? []).filter((u) => u.id !== l.product.base_uom_id && !(l.product.uoms ?? []).some((x) => x.uom_id === u.id)).map((u) => (<option key={u.id} value={u.id}>{u.code}</option>))}
                          </optgroup>
                        </select>
                        {needsFactor(l) && (
                          <div className="mt-1 flex items-center gap-1 text-xs text-slate-600">
                            1 = <input value={l.factor} inputMode="numeric" onChange={(e) => setLines(lines.map((x) => (x.key === l.key ? { ...x, factor: e.target.value.replace(/\D/g, '') } : x)))} className="ui-input h-7 w-16 tabular text-right" placeholder="30" /> {l.product.base_uom?.code ?? 'base units'}
                          </div>
                        )}
                      </td>
                      <td><input value={l.qty} inputMode="decimal" onChange={(e) => setLines(lines.map((x) => (x.key === l.key ? { ...x, qty: e.target.value.replace(/[^\d.]/g, '') } : x)))} className="ui-input h-7 w-20 tabular text-right font-bold" /></td>
                      <td><input value={l.notes} onChange={(e) => setLines(lines.map((x) => (x.key === l.key ? { ...x, notes: e.target.value } : x)))} className="ui-input h-7" /></td>
                      <td className="text-right"><Button size="xs" variant="ghost" onClick={() => setLines(lines.filter((x) => x.key !== l.key))} aria-label="Remove"><Trash2 size={13} /></Button></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
          {lines.some((l) => !l.uom_id) && <p className="text-xs text-amber-700">Choose a unit for every product.</p>}
          {lines.some(needsFactor) && <p className="text-xs text-slate-500">A unit the product does not have yet is added to it with the pack size you give, so it can be ordered and received in that unit.</p>}
        </FormSection>

        <FormSection title="Suppliers to ask" description="Only active suppliers can be invited" icon={Users} badge={`${supplierIds.length} chosen`}>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
            {usable.map((s) => {
              const expired = s.licence_expiry !== null && s.licence_expiry < new Date().toISOString().slice(0, 10)
              return (
                <label key={s.id} className="flex items-center gap-2 text-sm px-2 py-1.5 rounded-lg hover:bg-slate-50 cursor-pointer">
                  <input type="checkbox" checked={supplierIds.includes(s.id)} onChange={(e) => setSupplierIds(e.target.checked ? [...supplierIds, s.id] : supplierIds.filter((id) => id !== s.id))} />
                  <span className="font-medium text-slate-900">{s.name}</span>
                  {expired && <span className="text-xs text-rose-600">licence expired</span>}
                </label>
              )
            })}
            {usable.length === 0 && <p className="text-xs text-slate-500">No active suppliers. Add one under Suppliers first.</p>}
          </div>
          {supplierIds.length > 0 && lines.length > 0 && (
            <div className="mt-3 space-y-1">
              <h4 className="text-xs font-bold text-slate-700">Which products to ask each supplier for</h4>
              <p className="text-xs text-slate-500">Everyone is asked for the whole list unless you untick. Each supplier's PDF lists only their ticked products.</p>
              <SupplierProductMatrix
                products={[...new Map(lines.map((l) => [l.product.id, { id: l.product.id, name: l.product.name }])).values()]}
                suppliers={usable.filter((s) => supplierIds.includes(s.id)).map((s) => ({ id: s.id, name: s.name }))}
                value={assignments}
                onChange={setAssignments}
              />
            </div>
          )}
        </FormSection>

        {create.isError && <InlineError error={create.error} />}
      </div>
    </Drawer>
  )
}

function RfqDrawer({ id, onClose }: { id: string | null; onClose: () => void }) {
  const queryClient = useQueryClient()
  const [editingAssignments, setEditingAssignments] = useState<Assignments | null>(null)
  const perms = usePermissions()
  const [tab, setTab] = useState<'request' | 'quotes' | 'analysis'>('request')
  const [cancelling, setCancelling] = useState(false)
  const [quoting, setQuoting] = useState<RfqInvite | null>(null)
  const rfq = useQuery({ queryKey: ['rfqs', id], queryFn: () => apiGet<Rfq>(`/api/rfqs/${id}`), enabled: !!id })

  useEffect(() => {
    if (rfq.data) setTab(['CLOSED', 'AWARDED'].includes(rfq.data.status) ? 'analysis' : rfq.data.status === 'SENT' ? 'quotes' : 'request')
    // Only when a different request is opened, not on every refresh.
  }, [rfq.data?.id])

  function refresh(r: Rfq, msg: string) {
    toast.success(`${r.doc_number} ${msg}`)
    queryClient.invalidateQueries({ queryKey: ['rfqs'] })
  }
  const send = useMutation({ mutationFn: () => apiPost<Rfq>(`/api/rfqs/${id}/send`), onSuccess: (r) => { refresh(r, 'marked as sent'); setTab('quotes') } })
  const close = useMutation({ mutationFn: () => apiPost<Rfq>(`/api/rfqs/${id}/close`), onSuccess: (r) => { refresh(r, 'closed for quotes'); setTab('analysis') } })
  const cancel = useMutation({ mutationFn: (reason: string) => apiPost<Rfq>(`/api/rfqs/${id}/cancel`, { reason }), onSuccess: (r) => { setCancelling(false); refresh(r, 'cancelled') } })

  const r = rfq.data
  const canManage = perms.has('rfq.manage')

  return (
    <Drawer
      open={!!id}
      onClose={onClose}
      title={r ? `${r.doc_number} · ${r.title}` : 'Request for quotation'}
      subtitle={r ? `Needed by ${formatDate(r.needed_by)}` : undefined}
      width={1180}
      actions={r ? (
        <div className="flex gap-2">
          {r.status === 'DRAFT' && canManage && <Button size="sm" variant="primary" disabled={send.isPending} onClick={() => send.mutate()}>Mark as sent</Button>}
          {r.status === 'SENT' && canManage && <Button size="sm" variant="primary" disabled={close.isPending} onClick={() => close.mutate()}>Close for quotes</Button>}
          {['DRAFT', 'SENT', 'CLOSED'].includes(r.status) && canManage && <Button size="sm" variant="danger" onClick={() => setCancelling(true)}>Cancel</Button>}
          <PdfDownloadButton url={`/api/rfqs/${r.id}/summary-pdf`} filename={`${r.doc_number}-CBA`} label="CBA summary PDF" />
        </div>
      ) : null}
    >
      {rfq.isLoading && <LoadingSkeleton />}
      {rfq.isError && <InlineError error={rfq.error} />}
      {(send.isError || close.isError) && <InlineError error={send.error ?? close.error} className="mb-3" />}
      {r && (
        <div className="space-y-4">
          <div className="flex items-center gap-2 flex-wrap">
            <StatusBadge status={r.status} />
            {r.cancel_reason && <span className="text-xs text-slate-500">Cancelled: {r.cancel_reason}</span>}
          </div>
          <div className="flex items-center gap-1.5 p-1 bg-slate-100/80 rounded-xl border border-slate-200/60 w-fit">
            {([['request', 'Request'], ['quotes', 'Quotes'], ['analysis', 'Bid analysis & award']] as const).map(([key, label]) => (
              <button key={key} type="button" onClick={() => setTab(key)} className={`h-8 px-4 rounded-lg text-xs font-bold transition-all ${tab === key ? 'bg-white text-slate-900 shadow-xs border border-slate-200/80' : 'text-slate-600 hover:text-slate-900'}`}>{label}</button>
            ))}
          </div>

          {tab === 'request' && (
            <div className="space-y-4">
              <DescriptionList items={[
                { label: 'Notes', value: r.notes ?? '—' },
                { label: 'Prepared by', value: r.creator?.name ?? '—' },
                { label: 'Sent', value: formatDateTime(r.sent_at) },
              ]} />
              <table className="ui-table">
                <thead><tr><th>#</th><th>Product</th><th>Unit</th><th className="text-right">Qty</th><th>Notes</th></tr></thead>
                <tbody>
                  {(r.lines ?? []).map((l, i) => (
                    <tr key={l.id}><td>{i + 1}</td><td className="font-semibold">{l.product?.name}</td><td>{l.uom?.code}</td><td className="text-right tabular">{formatQty(l.qty)}</td><td>{l.notes ?? '—'}</td></tr>
                  ))}
                </tbody>
              </table>
              <div>
                <h3 className="text-sm font-bold text-slate-900 mb-2">Suppliers asked</h3>
                <table className="ui-table">
                  <thead><tr><th>Supplier</th><th>Email</th><th>Quote</th><th /></tr></thead>
                  <tbody>
                    {(r.suppliers ?? []).map((s) => (
                      <tr key={s.id}>
                        <td className="font-semibold">{s.supplier?.name}</td>
                        <td>
                          {s.supplier?.email ?? <span className="text-amber-700">no email</span>}
                          <div className="text-xs text-slate-500">{s.product_ids == null ? `Asked for all ${r.lines?.length ?? 0} products` : `Asked for ${s.product_ids.length} of ${r.lines?.length ?? 0} products`}</div>
                        </td>
                        <td><StatusBadge status={s.quote_status} tone={s.quote_status === 'RECEIVED' ? 'green' : s.quote_status === 'DECLINED' ? 'slate' : 'amber'} /></td>
                        <td className="text-right"><PdfDownloadButton url={`/api/rfqs/${r.id}/suppliers/${s.supplier_id}/pdf`} filename={`${r.doc_number}-${s.supplier?.code ?? ''}`} label="RFQ PDF" /></td>
                      </tr>
                    ))}
                  </tbody>
                </table>
                <p className="text-xs text-slate-500 mt-2">Download each supplier's request and send it to them. Enter their reply under Quotes.</p>
              </div>
              <AssignmentsSection rfq={r} editing={editingAssignments} setEditing={setEditingAssignments} />
            </div>
          )}

          {tab === 'quotes' && (
            <div className="space-y-3">
              {r.status === 'DRAFT' && <p className="text-sm text-slate-600">Mark the request as sent before entering quotes.</p>}
              <table className="ui-table">
                <thead><tr><th>Supplier</th><th>Status</th><th>Reference</th><th>Valid until</th><th className="text-right">Terms</th><th className="text-right">Delivery</th><th className="text-right">Lines quoted</th><th /></tr></thead>
                <tbody>
                  {(r.suppliers ?? []).map((s) => (
                    <tr key={s.id}>
                      <td className="font-semibold">{s.supplier?.name}</td>
                      <td><StatusBadge status={s.quote_status} tone={s.quote_status === 'RECEIVED' ? 'green' : s.quote_status === 'DECLINED' ? 'slate' : 'amber'} /></td>
                      <td>{s.quote_reference ?? '—'}</td>
                      <td>{formatDate(s.valid_until)}</td>
                      <td className="text-right tabular">{s.payment_terms_days != null ? `${s.payment_terms_days} d` : '—'}</td>
                      <td className="text-right tabular">{s.quote_status === 'RECEIVED' ? formatKes(s.delivery_charge) : '—'}</td>
                      <td className="text-right tabular">{s.quote_lines?.length ?? 0} of {s.product_ids?.length ?? r.lines?.length ?? 0}</td>
                      <td className="text-right">{canManage && ['SENT', 'CLOSED'].includes(r.status) && <Button size="xs" variant="primary" onClick={() => setQuoting(s)}>{s.quote_status === 'AWAITING' ? 'Enter quote' : 'Edit quote'}</Button>}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {tab === 'analysis' && <AnalysisPanel rfq={r} />}
        </div>
      )}
      <ConfirmDialog open={cancelling} title="Cancel request" confirmLabel="Cancel request" danger requireReason="Reason" isPending={cancel.isPending} onCancel={() => setCancelling(false)} onConfirm={(reason) => cancel.mutate(reason)} />
      {r && quoting && <QuoteModal rfq={r} invite={quoting} onClose={() => setQuoting(null)} />}
    </Drawer>
  )
}


/** supplier_id → the product ids that supplier is asked for; a missing entry (or null) is the whole list. */
type Assignments = Record<string, string[] | null>

/**
 * Who is asked for what: one row per product, one column per supplier. Each
 * supplier's PDF and quote cover only the products ticked in its column.
 */
function SupplierProductMatrix({ products, suppliers, value, onChange, disabled = false }: {
  products: { id: string; name: string }[]
  suppliers: { id: string; name: string }[]
  value: Assignments
  onChange: (next: Assignments) => void
  disabled?: boolean
}) {
  const asked = (supplierId: string, productId: string) => { const list = value[supplierId]; return list == null || list.includes(productId) }
  const toggle = (supplierId: string, productId: string, on: boolean) => {
    const current = value[supplierId] ?? products.map((p) => p.id)
    const next = on ? [...new Set([...current, productId])] : current.filter((id) => id !== productId)
    onChange({ ...value, [supplierId]: next.length === products.length ? null : next })
  }
  const setColumn = (supplierId: string, on: boolean) => onChange({ ...value, [supplierId]: on ? null : [] })
  const nobody = products.filter((p) => !suppliers.some((s) => asked(s.id, p.id)))
  const empty = suppliers.filter((s) => !products.some((p) => asked(s.id, p.id)))

  if (products.length === 0 || suppliers.length === 0) return null
  return (
    <div className="space-y-2">
      <div className="overflow-x-auto rounded-xl border border-slate-200">
        <table className="ui-table">
          <thead>
            <tr>
              <th>Product</th>
              {suppliers.map((s) => (
                <th key={s.id} className="text-center whitespace-nowrap">
                  <div>{s.name}</div>
                  {!disabled && (
                    <div className="text-[10px] font-normal space-x-1">
                      <button type="button" className="text-blue-600 hover:underline" onClick={() => setColumn(s.id, true)}>all</button>
                      <button type="button" className="text-blue-600 hover:underline" onClick={() => setColumn(s.id, false)}>none</button>
                    </div>
                  )}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {products.map((p) => (
              <tr key={p.id}>
                <td className="font-medium">{p.name}</td>
                {suppliers.map((s) => (
                  <td key={s.id} className="text-center">
                    <input type="checkbox" disabled={disabled} checked={asked(s.id, p.id)} onChange={(e) => toggle(s.id, p.id, e.target.checked)} aria-label={`Ask ${s.name} for ${p.name}`} />
                  </td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {nobody.length > 0 && <p className="text-xs text-amber-700">No supplier is asked for: {nobody.map((p) => p.name).join(', ')}.</p>}
      {empty.length > 0 && <p className="text-xs text-rose-600">{empty.map((s) => s.name).join(', ')} {empty.length === 1 ? 'is' : 'are'} not asked for anything — tick at least one product or remove the supplier.</p>}
    </div>
  )
}

/** Only the explicit lists are sent; a supplier asked for everything needs no entry. */
const assignmentPayload = (value: Assignments, supplierIds: string[]) =>
  Object.fromEntries(supplierIds.filter((id) => value[id] != null).map((id) => [id, value[id]]))

const matrixValid = (value: Assignments, supplierIds: string[]) => supplierIds.every((id) => value[id] == null || (value[id] ?? []).length > 0)

/** Each supplier's products, editable until quotes start coming in. */
function AssignmentsSection({ rfq, editing, setEditing }: { rfq: Rfq; editing: Assignments | null; setEditing: (v: Assignments | null) => void }) {
  const queryClient = useQueryClient()
  const perms = usePermissions()
  const invites = rfq.suppliers ?? []
  const current: Assignments = Object.fromEntries(invites.map((s) => [s.supplier_id, s.product_ids ?? null]))
  const supplierIds = invites.map((s) => s.supplier_id)
  const products = [...new Map((rfq.lines ?? []).map((l) => [l.product_id, { id: l.product_id, name: l.product?.name ?? l.product_id.slice(0, 8) }])).values()]
  const canEdit = perms.has('rfq.manage') && ['DRAFT', 'SENT'].includes(rfq.status)
  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPatch<Rfq>(`/api/rfqs/${rfq.id}`, { supplier_products: Object.fromEntries(supplierIds.map((id) => [id, editing?.[id] ?? null])) }),
    onSuccess: () => { toast.success('Supplier products saved', 'Download the PDFs again: each lists only that supplier\'s products.'); setEditing(null); queryClient.invalidateQueries({ queryKey: ['rfqs'] }) },
  })
  if (invites.length === 0) return null

  return (
    <div className="space-y-2">
      <div className="flex items-center gap-2">
        <h3 className="text-sm font-bold text-slate-900">Which products each supplier is asked for</h3>
        {canEdit && !editing && <Button size="sm" className="ml-auto" onClick={() => { save.reset(); setEditing(current) }}>Change</Button>}
      </div>
      <SupplierProductMatrix
        products={products}
        suppliers={invites.map((s) => ({ id: s.supplier_id, name: s.supplier?.name ?? s.supplier_id.slice(0, 8) }))}
        value={editing ?? current}
        onChange={setEditing}
        disabled={!editing}
      />
      {editing && (
        <div className="flex justify-end gap-2">
          {save.isError && <InlineError error={save.error} />}
          <Button onClick={() => setEditing(null)}>Cancel</Button>
          <Button variant="primary" disabled={!matrixValid(editing, supplierIds) || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : 'Save'}</Button>
        </div>
      )}
    </div>
  )
}

type QuoteRow = { rfq_line_id: string; unit_price: string; qty_available: string; lead_time_days: string; shelf_life_months: string; notes: string }

function QuoteModal({ rfq, invite, onClose }: { rfq: Rfq; invite: RfqInvite; onClose: () => void }) {
  const queryClient = useQueryClient()
  const defaultLead = invite.supplier?.lead_time_days != null ? String(invite.supplier.lead_time_days) : ''
  const [declined, setDeclined] = useState(invite.quote_status === 'DECLINED')
  const [reference, setReference] = useState(invite.quote_reference ?? '')
  const [quoteDate, setQuoteDate] = useState(invite.quote_date ?? '')
  const [validUntil, setValidUntil] = useState(invite.valid_until ?? '')
  const [terms, setTerms] = useState(invite.payment_terms_days != null ? String(invite.payment_terms_days) : invite.supplier?.payment_terms_days != null ? String(invite.supplier.payment_terms_days) : '')
  const [delivery, setDelivery] = useState(invite.quote_status === 'RECEIVED' ? String(Number(invite.delivery_charge)) : '')
  const [notes, setNotes] = useState(invite.notes ?? '')
  const askedLines = (rfq.lines ?? []).filter((l) => invite.product_ids == null || invite.product_ids.includes(l.product_id))
  const [rows, setRows] = useState<QuoteRow[]>(() => askedLines.map((l) => {
    const existing = invite.quote_lines?.find((q) => q.rfq_line_id === l.id)
    return {
      rfq_line_id: l.id,
      unit_price: existing ? String(Number(existing.unit_price)) : '',
      qty_available: existing?.qty_available ? String(Number(existing.qty_available)) : '',
      lead_time_days: existing ? String(existing.lead_time_days) : defaultLead,
      shelf_life_months: existing?.shelf_life_months != null ? String(existing.shelf_life_months) : '',
      notes: existing?.notes ?? '',
    }
  }))

  const quoted = rows.filter((row) => row.unit_price !== '')
  const valid = declined || (quoted.length > 0 && quoted.every((row) => /^\d+(\.\d+)?$/.test(row.unit_price) && /^\d+$/.test(row.lead_time_days)))

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPut<Rfq>(`/api/rfqs/${rfq.id}/quotes/${invite.supplier_id}`, declined ? { declined: true, notes: notes || null } : {
      quote_reference: reference || null,
      quote_date: quoteDate || null,
      valid_until: validUntil || null,
      payment_terms_days: terms === '' ? null : Number(terms),
      delivery_charge: delivery === '' ? 0 : delivery,
      notes: notes || null,
      lines: quoted.map((row) => ({
        rfq_line_id: row.rfq_line_id,
        unit_price: row.unit_price,
        qty_available: row.qty_available === '' ? null : row.qty_available,
        lead_time_days: Number(row.lead_time_days),
        shelf_life_months: row.shelf_life_months === '' ? null : Number(row.shelf_life_months),
        notes: row.notes || null,
      })),
    }),
    onSuccess: () => {
      toast.success(`Quote from ${invite.supplier?.name} saved`)
      queryClient.invalidateQueries({ queryKey: ['rfqs'] })
      onClose()
    },
  })

  const set = (i: number, patch: Partial<QuoteRow>) => setRows(rows.map((row, j) => (j === i ? { ...row, ...patch } : row)))

  return (
    <Modal
      open
      onClose={onClose}
      title={`Quote from ${invite.supplier?.name ?? 'supplier'}`}
      width={960}
      footer={<><Button onClick={onClose}>Cancel</Button><Button variant="primary" disabled={!valid || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : 'Save quote'}</Button></>}
    >
      <div className="space-y-3">
        <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={declined} onChange={(e) => setDeclined(e.target.checked)} /> The supplier declined to quote</label>
        {!declined && (
          <>
            <div className="grid grid-cols-2 sm:grid-cols-5 gap-3">
              <Field label="Their reference"><Input value={reference} onChange={(e) => setReference(e.target.value)} /></Field>
              <Field label="Quote date"><Input type="date" value={quoteDate} onChange={(e) => setQuoteDate(e.target.value)} /></Field>
              <Field label="Valid until"><Input type="date" value={validUntil} onChange={(e) => setValidUntil(e.target.value)} /></Field>
              <Field label="Payment terms (days)"><Input value={terms} inputMode="numeric" onChange={(e) => setTerms(e.target.value.replace(/\D/g, ''))} /></Field>
              <Field label="Delivery charge (KES)"><Input value={delivery} inputMode="decimal" onChange={(e) => setDelivery(e.target.value.replace(/[^\d.]/g, ''))} /></Field>
            </div>
            <table className="ui-table">
              <thead><tr><th>Product</th><th className="text-right">Qty needed</th><th className="text-right">Unit price (KES)</th><th className="text-right">Qty they can supply</th><th className="text-right">Lead time (days)</th><th className="text-right">Shelf life (months)</th><th>Notes</th></tr></thead>
              <tbody>
                {askedLines.map((l, i) => (
                  <tr key={l.id}>
                    <td><div className="font-semibold">{l.product?.name}</div><div className="text-xs text-slate-500">{l.uom?.code}</div></td>
                    <td className="text-right tabular">{formatQty(l.qty)}</td>
                    <td><input value={rows[i].unit_price} inputMode="decimal" placeholder="not quoted" onChange={(e) => set(i, { unit_price: e.target.value.replace(/[^\d.]/g, '') })} className="ui-input h-7 w-28 tabular text-right font-bold" /></td>
                    <td><input value={rows[i].qty_available} inputMode="decimal" placeholder="all" onChange={(e) => set(i, { qty_available: e.target.value.replace(/[^\d.]/g, '') })} className="ui-input h-7 w-24 tabular text-right" /></td>
                    <td><input value={rows[i].lead_time_days} inputMode="numeric" onChange={(e) => set(i, { lead_time_days: e.target.value.replace(/\D/g, '') })} className="ui-input h-7 w-20 tabular text-right" /></td>
                    <td><input value={rows[i].shelf_life_months} inputMode="numeric" placeholder="—" onChange={(e) => set(i, { shelf_life_months: e.target.value.replace(/\D/g, '') })} className="ui-input h-7 w-20 tabular text-right" /></td>
                    <td><input value={rows[i].notes} onChange={(e) => set(i, { notes: e.target.value })} className="ui-input h-7" /></td>
                  </tr>
                ))}
              </tbody>
            </table>
            <p className="text-xs text-slate-500">Leave the price blank for a line they did not quote. Leave "qty they can supply" blank when they can supply it all.</p>
          </>
        )}
        <Field label="Notes"><Textarea rows={2} value={notes} onChange={(e) => setNotes(e.target.value)} /></Field>
        {save.isError && <InlineError error={save.error} />}
      </div>
    </Modal>
  )
}

function RiskList({ risks }: { risks: CbaRisk[] }) {
  if (risks.length === 0) return null
  return (
    <ul className="space-y-0.5">
      {risks.map((risk, i) => (
        <li key={`${risk.code}-${i}`} className="flex items-start gap-1.5 text-xs text-amber-800"><AlertTriangle size={12} className="mt-0.5 shrink-0" />{risk.message}</li>
      ))}
    </ul>
  )
}

function AnalysisPanel({ rfq }: { rfq: Rfq }) {
  const queryClient = useQueryClient()
  const perms = usePermissions()
  const analysis = useQuery({ queryKey: ['rfqs', rfq.id, 'analysis', rfq.status], queryFn: () => apiGet<CbaAnalysis>(`/api/rfqs/${rfq.id}/analysis`) })
  const [weights, setWeights] = useState({ price: '', lead_time: '', payment_terms: '', supplier_record: '' })
  const [choices, setChoices] = useState<Record<string, string>>({})
  const [manualPrices, setManualPrices] = useState<Record<string, string>>({})
  const [justification, setJustification] = useState('')
  const allSuppliers = useSuppliers()

  const a = analysis.data
  const plan = useMemo<Record<string, string>>(() => (a && !Array.isArray(a.overall.plan) ? a.overall.plan : {}), [a])

  useEffect(() => {
    setWeights({ price: String(Number(rfq.weight_price)), lead_time: String(Number(rfq.weight_lead_time)), payment_terms: String(Number(rfq.weight_payment_terms)), supplier_record: String(Number(rfq.weight_supplier_record)) })
  }, [rfq.weight_price, rfq.weight_lead_time, rfq.weight_payment_terms, rfq.weight_supplier_record])
  useEffect(() => { setChoices(plan) }, [plan])

  const saveWeights = useMutation({
    mutationFn: () => apiPatch<Rfq>(`/api/rfqs/${rfq.id}`, { weights: { price: Number(weights.price), lead_time: Number(weights.lead_time), payment_terms: Number(weights.payment_terms), supplier_record: Number(weights.supplier_record) } }),
    onSuccess: () => { toast.success('Weights saved', 'The analysis has been re-scored.'); queryClient.invalidateQueries({ queryKey: ['rfqs'] }) },
  })
  const award = useMutation({
    meta: { silent: true },
    mutationFn: (followRecommendation: boolean) => apiPost<Rfq>(`/api/rfqs/${rfq.id}/award`, followRecommendation ? {} : {
      lines: (a?.lines ?? []).map((l) => ({ rfq_line_id: l.rfq_line_id, supplier_id: choices[l.rfq_line_id] || null, unit_price: manualPrices[l.rfq_line_id] || null })),
      justification: justification || null,
    }),
    onSuccess: (r) => {
      toast.success(`${r.doc_number} awarded`, `${r.created_purchase_orders?.length ?? 0} draft purchase order(s) raised for approval.`)
      queryClient.invalidateQueries({ queryKey: ['rfqs'] })
      queryClient.invalidateQueries({ queryKey: ['purchase-orders'] })
    },
  })

  if (analysis.isLoading) return <LoadingSkeleton />
  if (analysis.isError) return <InlineError error={analysis.error} />
  if (!a) return null

  const weightSum = Object.values(weights).reduce((sum, v) => sum + (Number(v) || 0), 0)
  // Any change from the plan is sent as explicit choices; only overriding a real recommendation needs a reason.
  const custom = a.lines.some((l) => (choices[l.rfq_line_id] || '') !== (plan[l.rfq_line_id] ?? ''))
  const departs = a.lines.some((l) => !!plan[l.rfq_line_id] && (choices[l.rfq_line_id] || '') !== plan[l.rfq_line_id])
  const anyAwarded = a.lines.some((l) => !!choices[l.rfq_line_id])
  const canAward = perms.has('rfq.award') && ['SENT', 'CLOSED'].includes(rfq.status)
  const activeSuppliers = (allSuppliers.data?.data ?? []).filter((s) => s.status === 'ACTIVE')
  const quotedBy = (lineId: string, supplierId: string) => !!a.lines.find((l) => l.rfq_line_id === lineId)?.quotes.some((q) => q.supplier_id === supplierId)
  const quoted = a.suppliers.filter((s) => s.quote_status === 'RECEIVED')
  const nameOf = (supplierId: string | undefined) => a.suppliers.find((s) => s.supplier_id === supplierId)?.name ?? '—'

  return (
    <div className="space-y-4">
      <div className={`rounded-xl border p-4 ${a.overall.mode === 'NONE' ? 'border-slate-200 bg-slate-50' : 'border-emerald-200 bg-emerald-50/60'}`}>
        <div className="flex items-center gap-2 text-sm font-bold text-slate-900">
          <Award size={16} className="text-emerald-700" />
          {a.overall.mode === 'SINGLE' && <>Recommended: one order from {a.overall.supplier_name}</>}
          {a.overall.mode === 'SPLIT' && <>Recommended: split the award</>}
          {a.overall.mode === 'NONE' && <>No recommendation yet</>}
          {a.overall.mode !== 'NONE' && <span className="ml-auto tabular">{formatKes(a.overall.total)} incl. delivery</span>}
        </div>
        <p className="text-sm text-slate-700 mt-1">{a.overall.summary}</p>
        {a.overall.mode === 'SPLIT' && (
          <ul className="text-xs text-slate-600 mt-1">
            {a.lines.filter((l) => plan[l.rfq_line_id]).map((l) => (<li key={l.rfq_line_id}>{l.product.name} → <strong>{nameOf(plan[l.rfq_line_id])}</strong></li>))}
          </ul>
        )}
        <div className="mt-2"><RiskList risks={a.overall.risks} /></div>
      </div>

      {rfq.status === 'AWARDED' && (
        <div className="rounded-xl border border-blue-200 bg-blue-50/60 p-4 text-sm space-y-1">
          <div className="font-bold text-slate-900 flex items-center gap-2"><CheckCircle2 size={16} className="text-blue-700" />Awarded by {rfq.awarder?.name ?? '—'} on {formatDateTime(rfq.awarded_at)}</div>
          <div>{rfq.award_followed_recommendation ? 'The recommendation was accepted.' : 'The award departs from the recommendation.'}</div>
          {rfq.award_justification && <div><span className="font-semibold">Justification:</span> {rfq.award_justification}</div>}
          <div className="flex flex-wrap gap-2 pt-1">
            {(rfq.purchase_orders ?? []).map((po) => (<Link key={po.id} to={`/buy/purchase-orders?po=${po.id}`} className="text-xs text-blue-600 hover:underline font-semibold">{po.doc_number} · {po.supplier?.name} · {titleCase(po.status)}</Link>))}
          </div>
        </div>
      )}

      <div className="overflow-x-auto rounded-xl border border-slate-200">
        <table className="ui-table min-w-[720px]">
          <thead>
            <tr>
              <th>Item</th>
              <th className="text-right">Qty</th>
              {quoted.map((s) => (<th key={s.supplier_id} className="text-right">{s.name}</th>))}
            </tr>
          </thead>
          <tbody>
            {a.lines.map((l) => (
              <tr key={l.rfq_line_id}>
                <td><div className="font-semibold">{l.product.name}</div><div className="text-xs text-slate-500">{l.uom.code}</div></td>
                <td className="text-right tabular">{formatQty(l.qty)}</td>
                {quoted.map((s) => {
                  const quote = l.quotes.find((x) => x.supplier_id === s.supplier_id)
                  if (!quote) return <td key={s.supplier_id} className="text-right text-xs text-slate-400">not quoted</td>
                  const isPlan = plan[l.rfq_line_id] === s.supplier_id
                  return (
                    <td key={s.supplier_id} className={`text-right align-top ${quote.is_lowest ? 'bg-emerald-50' : ''}`}>
                      <div className={`tabular font-bold ${isPlan ? 'text-emerald-700' : 'text-slate-900'} ${!quote.eligible ? 'line-through text-slate-400' : ''}`}>{formatKes(quote.unit_price, { symbol: false })}{isPlan && ' ✓'}</div>
                      <div className="text-xs text-slate-500 tabular">score {quote.scores.total.toFixed(1)} · {quote.lead_time_days} d</div>
                      {!quote.full_quantity && <div className="text-xs text-amber-700">only {formatQty(quote.qty_available)}</div>}
                    </td>
                  )
                })}
              </tr>
            ))}
          </tbody>
          <tfoot>
            <tr><td colSpan={2} className="text-xs font-semibold">Goods + delivery</td>{quoted.map((s) => (<td key={s.supplier_id} className="text-right tabular text-xs font-semibold">{formatKes(s.total_with_delivery)}</td>))}</tr>
            <tr><td colSpan={2} className="text-xs">Payment terms · valid until</td>{quoted.map((s) => (<td key={s.supplier_id} className={`text-right text-xs ${s.quote_valid ? '' : 'text-rose-600'}`}>{s.payment_terms_days ?? 0} d · {formatDate(s.valid_until)}</td>))}</tr>
            <tr><td colSpan={2} className="text-xs">Supplier record (out of 100)</td>{quoted.map((s) => (<td key={s.supplier_id} className="text-right text-xs tabular">{s.record.score.toFixed(1)}</td>))}</tr>
          </tfoot>
        </table>
      </div>
      <p className="text-xs text-slate-500">Green cell: lowest price on the line. ✓ recommended. Struck through: the supplier cannot be ordered from.</p>

      <div className="space-y-2">
        <h3 className="text-sm font-bold text-slate-900 flex items-center gap-2"><Scale size={15} />Why, line by line</h3>
        {a.lines.map((l) => (
          <div key={l.rfq_line_id} className="rounded-lg border border-slate-200 p-3">
            <div className="text-sm"><span className="font-semibold">{l.product.name}</span>{l.recommendation ? <> → <span className="font-bold text-emerald-700">{l.recommendation.supplier_name}</span> <span className="text-xs text-slate-500 tabular">({formatKes(l.recommendation.line_total)}, score {l.recommendation.score.toFixed(1)})</span></> : null}</div>
            <p className="text-xs text-slate-600 mt-0.5">{l.recommendation?.summary ?? l.no_recommendation_reason}</p>
            {l.recommendation && <div className="mt-1"><RiskList risks={l.recommendation.risks} /></div>}
          </div>
        ))}
      </div>

      <div className="space-y-2">
        <h3 className="text-sm font-bold text-slate-900">Suppliers' records</h3>
        {a.suppliers.map((s) => (
          <div key={s.supplier_id} className="text-xs"><span className="font-semibold text-slate-900">{s.name}</span> <span className="text-slate-600">— {s.record.summary}</span><RiskList risks={s.risks} /></div>
        ))}
      </div>

      {perms.has('rfq.manage') && rfq.status !== 'AWARDED' && rfq.status !== 'CANCELLED' && (
        <div className="rounded-xl border border-slate-200 p-3 space-y-2">
          <h3 className="text-sm font-bold text-slate-900">Score weights (%)</h3>
          <div className="grid grid-cols-2 sm:grid-cols-5 gap-3 items-end">
            {([['price', 'Price'], ['lead_time', 'Lead time'], ['payment_terms', 'Payment terms'], ['supplier_record', 'Supplier record']] as const).map(([key, label]) => (
              <Field key={key} label={label}><Input value={weights[key]} inputMode="decimal" onChange={(e) => setWeights({ ...weights, [key]: e.target.value.replace(/[^\d.]/g, '') })} /></Field>
            ))}
            <Button variant="secondary" disabled={Math.abs(weightSum - 100) > 0.01 || saveWeights.isPending} onClick={() => saveWeights.mutate()}>{Math.abs(weightSum - 100) > 0.01 ? `Adds to ${weightSum}%` : 'Save and re-score'}</Button>
          </div>
          {saveWeights.isError && <InlineError error={saveWeights.error} />}
        </div>
      )}

      {canAward && (
        <div className="rounded-xl border border-blue-200 p-4 space-y-3">
          <h3 className="text-sm font-bold text-slate-900 flex items-center gap-2"><Award size={15} />Assign suppliers and convert to purchase orders</h3>
          <p className="text-xs text-slate-600">Choose who supplies each product. If a supplier does not have an item, give it to another one — any active supplier can be picked, even one that did not quote it; enter the agreed price, or leave it blank for the supplier to confirm.</p>
          <table className="ui-table">
            <thead><tr><th>Item</th><th>Recommended</th><th>Supplier</th><th className="text-right">Unit price</th></tr></thead>
            <tbody>
              {a.lines.map((l) => {
                const differs = !!plan[l.rfq_line_id] && (choices[l.rfq_line_id] || '') !== plan[l.rfq_line_id]
                const chosen = choices[l.rfq_line_id] ?? ''
                const quotedIds = new Set(l.quotes.map((x) => x.supplier_id))
                const chosenQuote = l.quotes.find((x) => x.supplier_id === chosen)
                return (
                  <tr key={l.rfq_line_id}>
                    <td className="font-semibold">{l.product.name}</td>
                    <td>{plan[l.rfq_line_id] ? nameOf(plan[l.rfq_line_id]) : '—'}</td>
                    <td>
                      <select value={choices[l.rfq_line_id] ?? ''} onChange={(e) => setChoices({ ...choices, [l.rfq_line_id]: e.target.value })} className={`ui-input h-8 w-auto ${differs ? 'border-amber-400' : ''}`}>
                        <option value="">Do not order</option>
                        {l.quotes.some((x) => x.eligible) && (
                          <optgroup label="Quoted this item">
                            {l.quotes.filter((x) => x.eligible).map((x) => (<option key={x.supplier_id} value={x.supplier_id}>{x.supplier_name} — {formatKes(x.unit_price)}</option>))}
                          </optgroup>
                        )}
                        <optgroup label="Other suppliers">
                          {activeSuppliers.filter((s) => !quotedIds.has(s.id)).map((s) => (<option key={s.id} value={s.id}>{s.name}</option>))}
                        </optgroup>
                      </select>
                      {differs && <span className="ml-2 text-xs text-amber-700">differs from the recommendation</span>}
                    </td>
                    <td className="text-right">
                      {chosen && !quotedBy(l.rfq_line_id, chosen)
                        ? <input value={manualPrices[l.rfq_line_id] ?? ''} onChange={(e) => setManualPrices({ ...manualPrices, [l.rfq_line_id]: e.target.value.replace(/[^\d.]/g, '') })} placeholder="to confirm" className="ui-input h-8 w-28 tabular text-right" />
                        : <span className="tabular text-sm">{chosenQuote ? formatKes(chosenQuote.unit_price) : '—'}</span>}
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
          {departs && (
            <Field label="Why are you departing from the recommendation?" required hint="At least 10 characters. This goes on the award summary and the audit log.">
              <Textarea rows={2} value={justification} onChange={(e) => setJustification(e.target.value)} />
            </Field>
          )}
          <div className="flex flex-wrap gap-2">
            {!custom && <Button variant="primary" disabled={award.isPending || !anyAwarded} onClick={() => award.mutate(true)}>{award.isPending ? 'Converting…' : 'Convert to purchase orders (as recommended)'}</Button>}
            {custom && <Button variant="primary" disabled={award.isPending || !anyAwarded || (departs && justification.trim().length < 10)} onClick={() => award.mutate(false)}>{award.isPending ? 'Converting…' : 'Convert to purchase orders'}</Button>}
            {custom && <Button onClick={() => { setChoices(plan); setManualPrices({}); setJustification('') }}>Back to the recommendation</Button>}
          </div>
          <p className="text-xs text-slate-500">One draft purchase order is raised per supplier, at the quoted (or entered) prices. Each still needs approval before it is sent.</p>
          {award.isError && <InlineError error={award.error} />}
        </div>
      )}
    </div>
  )
}
