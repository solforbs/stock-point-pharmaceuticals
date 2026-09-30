import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Plus, Trash2 } from 'lucide-react'
import { useState } from 'react'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { PostingImpact } from '../../components/PostingImpact'
import { Drawer } from '../../components/ui/Drawer'
import { ConfirmDialog, Modal } from '../../components/ui/Modal'
import { MoneyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, Field, Input, Select, Textarea } from '../../components/ui/primitives'
import { apiGet, apiPost } from '../../lib/api'
import { dMul, dSum, isValidDecimal } from '../../lib/decimal'
import { formatDate, formatDateTime, titleCase } from '../../lib/format'
import { formatMoney } from '../../lib/money'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Paginated, SupplyRequest } from '../../lib/types'

const CATEGORIES = ['CLEANING', 'STATIONERY', 'OFFICE', 'PACKAGING', 'UNIFORMS', 'OTHER']
const PAID_FROM = [
  { value: 'PETTY_CASH', label: 'Petty cash' },
  { value: 'CASH', label: 'Cash in till' },
  { value: 'BANK', label: 'Bank' },
  { value: 'MPESA', label: 'M-PESA' },
]
const STATUSES = ['DRAFT', 'PENDING_APPROVAL', 'APPROVED', 'PURCHASED', 'REJECTED']

type DraftLine = { key: number; item: string; category: string; qty: string; unit: string; est: string }
const blankLine = (key: number): DraftLine => ({ key, item: '', category: 'CLEANING', qty: '1', unit: 'pcs', est: '' })

/**
 * Non-pharmaceutical supplies: brooms, mops, cleaning products, stationery.
 * They are requested, approved by someone else and then bought; the cost is
 * expensed the moment the purchase is recorded, and none of it becomes stock.
 */
export default function SupplyRequestsPage() {
  const queryClient = useQueryClient()
  const canCreate = usePermission('requisition.create')
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const [openId, setOpenId] = useState<string | null>(null)

  const list = useQuery({
    queryKey: ['supply-requests', status, page],
    queryFn: () => apiGet<Paginated<SupplyRequest>>('/api/supply-requests', { status: status || undefined, page, per_page: 25 }),
    placeholderData: (prev) => prev,
  })
  const refresh = () => {
    queryClient.invalidateQueries({ queryKey: ['supply-requests'] })
    queryClient.invalidateQueries({ queryKey: ['finance'] })
  }

  const columns: Column<SupplyRequest>[] = [
    { key: 'doc', header: 'Request', render: (r) => <span className="tabular font-mono font-semibold">{r.doc_number}</span> },
    { key: 'date', header: 'Raised', render: (r) => formatDate(r.created_at) },
    { key: 'by', header: 'By', render: (r) => r.requester?.name ?? '—' },
    { key: 'needed', header: 'Needed by', render: (r) => formatDate(r.needed_by) },
    { key: 'lines', header: 'Items', align: 'right', render: (r) => r.lines_count ?? 0 },
    { key: 'total', header: 'Cost', align: 'right', render: (r) => <MoneyCell value={r.total_cost} />, sortValue: (r) => Number(r.total_cost) },
    { key: 'status', header: 'Status', render: (r) => <StatusBadge status={r.status} /> },
  ]

  return (
    <Page>
      <PageHeader
        parent="Buy"
        title="Supplies"
        subtitle="Brooms, mops, cleaning products, stationery and other everyday items that are not medicines. Request them here; once approved and bought, the cost goes straight to expenses and none of it is counted as stock."
        actions={canCreate ? <Button variant="primary" onClick={() => setCreating(true)}><Plus size={14} /> New supply request</Button> : null}
      />
      <FilterBar>
        <Field label="Status" className="w-56">
          <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
            <option value="">All</option>
            {STATUSES.map((s) => (<option key={s} value={s}>{titleCase(s)}</option>))}
          </Select>
        </Field>
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(r) => r.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(r) => setOpenId(r.id)} selectedKey={openId} emptyTitle="No supply requests" emptyHint="Raise one when the cleaning cupboard or the stationery drawer runs low." />
        <Pagination page={list.data} onPage={setPage} />
      </div>

      <NewRequestModal open={creating} onClose={() => setCreating(false)} onCreated={(r) => { setCreating(false); refresh(); setOpenId(r.id) }} />
      <RequestDrawer id={openId} onClose={() => setOpenId(null)} onChanged={refresh} />
    </Page>
  )
}

function NewRequestModal({ open, onClose, onCreated }: { open: boolean; onClose: () => void; onCreated: (r: SupplyRequest) => void }) {
  const [lines, setLines] = useState<DraftLine[]>([blankLine(1)])
  const [neededBy, setNeededBy] = useState('')
  const [notes, setNotes] = useState('')
  const valid = lines.every((l) => l.item.trim() && isValidDecimal(l.qty) && Number(l.qty) > 0 && (l.est === '' || isValidDecimal(l.est)))
  const estimate = dSum(lines.map((l) => (isValidDecimal(l.qty) && isValidDecimal(l.est || '0') ? dMul(l.qty, l.est || '0') : '0')))

  const create = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPost<SupplyRequest>('/api/supply-requests', {
        needed_by: neededBy || null,
        notes: notes || null,
        lines: lines.map((l) => ({ item: l.item.trim(), category: l.category, qty: l.qty, unit: l.unit || 'pcs', est_unit_cost: l.est || 0 })),
      }),
    onSuccess: (r) => {
      toast.success(`${r.doc_number} created`, 'Submit it for approval when it is complete.')
      setLines([blankLine(1)])
      setNeededBy('')
      setNotes('')
      onCreated(r)
    },
  })
  const set = (key: number, patch: Partial<DraftLine>) => setLines(lines.map((l) => (l.key === key ? { ...l, ...patch } : l)))

  return (
    <Modal
      open={open}
      onClose={onClose}
      width={860}
      title="New supply request"
      footer={<><Button onClick={onClose}>Cancel</Button><Button variant="primary" disabled={!valid || create.isPending} onClick={() => create.mutate()}>{create.isPending ? 'Saving…' : 'Save request'}</Button></>}
    >
      <div className="space-y-3">
        <div className="overflow-x-auto">
          <table className="ui-table">
            <thead><tr><th>Item</th><th>Type</th><th className="text-right">Qty</th><th>Unit</th><th className="text-right">Est. cost each</th><th /></tr></thead>
            <tbody>
              {lines.map((l) => (
                <tr key={l.key}>
                  <td><input className="ui-input h-8 w-52" placeholder="e.g. Broom, Mop head, A4 paper" value={l.item} onChange={(e) => set(l.key, { item: e.target.value })} /></td>
                  <td><select className="ui-input h-8" value={l.category} onChange={(e) => set(l.key, { category: e.target.value })}>{CATEGORIES.map((c) => (<option key={c} value={c}>{titleCase(c)}</option>))}</select></td>
                  <td><input inputMode="decimal" className="ui-input h-8 w-20 tabular text-right" value={l.qty} onChange={(e) => set(l.key, { qty: e.target.value.replace(/[^\d.]/g, '') })} /></td>
                  <td><input className="ui-input h-8 w-20" value={l.unit} onChange={(e) => set(l.key, { unit: e.target.value })} /></td>
                  <td><input inputMode="decimal" className="ui-input h-8 w-28 tabular text-right" value={l.est} onChange={(e) => set(l.key, { est: e.target.value.replace(/[^\d.]/g, '') })} /></td>
                  <td>{lines.length > 1 && <button type="button" aria-label="Remove line" className="text-slate-400 hover:text-rose-600" onClick={() => setLines(lines.filter((x) => x.key !== l.key))}><Trash2 size={14} /></button>}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <div className="flex items-center justify-between">
          <Button size="sm" onClick={() => setLines([...lines, blankLine(Math.max(...lines.map((l) => l.key)) + 1)])}><Plus size={12} /> Add item</Button>
          <div className="text-xs text-slate-600">Estimated total <b className="tabular">{formatMoney(estimate)}</b></div>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <Field label="Needed by"><Input type="date" value={neededBy} onChange={(e) => setNeededBy(e.target.value)} /></Field>
          <Field label="Notes"><Textarea rows={1} value={notes} onChange={(e) => setNotes(e.target.value)} /></Field>
        </div>
        {create.isError && <InlineError error={create.error} />}
      </div>
    </Modal>
  )
}

function RequestDrawer({ id, onClose, onChanged }: { id: string | null; onClose: () => void; onChanged: () => void }) {
  const canCreate = usePermission('requisition.create')
  const canApprove = usePermission('requisition.approve')
  const canBuy = usePermission('po.create')
  const [rejecting, setRejecting] = useState(false)
  const [buying, setBuying] = useState(false)

  const request = useQuery({ queryKey: ['supply-requests', 'one', id], queryFn: () => apiGet<SupplyRequest>(`/api/supply-requests/${id}`), enabled: !!id })
  const r = request.data
  const act = useMutation({
    meta: { silent: true },
    mutationFn: ({ action, body }: { action: string; body?: unknown }) => apiPost<SupplyRequest>(`/api/supply-requests/${id}/${action}`, body),
    onSuccess: (updated) => {
      toast.success(`${updated.doc_number} is now ${titleCase(updated.status)}`)
      setRejecting(false)
      request.refetch()
      onChanged()
    },
  })

  return (
    <Drawer open={!!id} onClose={onClose} title={r ? r.doc_number : 'Supply request'} subtitle={r ? `${r.requester?.name ?? ''} · raised ${formatDateTime(r.created_at)}` : undefined} width={720}>
      {request.isLoading && <LoadingSkeleton />}
      {request.isError && <InlineError error={request.error} />}
      {r && (
        <div className="space-y-4">
          <div className="flex items-center justify-between gap-2">
            <StatusBadge status={r.status} />
            <div className="flex gap-2">
              {r.status === 'DRAFT' && canCreate && <Button variant="primary" size="sm" disabled={act.isPending} onClick={() => act.mutate({ action: 'submit' })}>Submit for approval</Button>}
              {r.status === 'PENDING_APPROVAL' && canApprove && (
                <>
                  <Button size="sm" variant="danger" disabled={act.isPending} onClick={() => setRejecting(true)}>Reject</Button>
                  <Button size="sm" variant="primary" disabled={act.isPending} onClick={() => act.mutate({ action: 'approve' })}>Approve</Button>
                </>
              )}
              {r.status === 'APPROVED' && canBuy && <Button size="sm" variant="primary" onClick={() => setBuying(true)}>Record purchase</Button>}
            </div>
          </div>
          {act.isError && <InlineError error={act.error} />}
          {r.status === 'REJECTED' && r.reject_reason && <div className="rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-800">Rejected: {r.reject_reason}</div>}
          {r.status === 'PURCHASED' && (
            <div className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-900">
              Bought {formatDateTime(r.purchased_at)}{r.supplier_name ? ` from ${r.supplier_name}` : ''}, paid from {titleCase(r.paid_from)}{r.payment_reference ? ` (${r.payment_reference})` : ''}. Posted Dr Cleaning and office supplies / Cr {titleCase(r.paid_from)}.
            </div>
          )}
          <div className="ui-card overflow-x-auto">
            <table className="ui-table">
              <thead><tr><th>Item</th><th>Type</th><th className="text-right">Qty</th><th className="text-right">Estimate</th><th className="text-right">Actual</th></tr></thead>
              <tbody>
                {(r.lines ?? []).map((l) => (
                  <tr key={l.id}>
                    <td className="font-medium">{l.item}</td>
                    <td>{titleCase(l.category)}</td>
                    <td className="text-right tabular">{Number(l.qty)} {l.unit}</td>
                    <td className="text-right"><MoneyCell value={l.est_unit_cost} /></td>
                    <td className="text-right">{l.actual_unit_cost ? <MoneyCell value={l.actual_unit_cost} /> : '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="text-right text-sm">{r.status === 'PURCHASED' ? 'Total spent' : 'Estimated total'}: <b className="tabular">{formatMoney(r.total_cost)}</b></div>
          {r.notes && <p className="text-xs text-slate-600">{r.notes}</p>}
          {r.status === 'PURCHASED' && <PostingImpact sourceId={r.id} />}
        </div>
      )}
      <ConfirmDialog open={rejecting} title="Reject this request?" requireReason="Reason for rejecting" confirmLabel="Reject" danger isPending={act.isPending} onCancel={() => setRejecting(false)} onConfirm={(reason) => act.mutate({ action: 'reject', body: { reason } })} />
      {r && <PurchaseModal open={buying} request={r} onClose={() => setBuying(false)} onDone={() => { setBuying(false); request.refetch(); onChanged() }} />}
    </Drawer>
  )
}

function PurchaseModal({ open, request, onClose, onDone }: { open: boolean; request: SupplyRequest; onClose: () => void; onDone: () => void }) {
  const lines = request.lines ?? []
  const [paidFrom, setPaidFrom] = useState('PETTY_CASH')
  const [supplier, setSupplier] = useState('')
  const [reference, setReference] = useState('')
  const [costs, setCosts] = useState<Record<string, string>>({})
  const cost = (id: string, fallback: string) => costs[id] ?? String(Number(fallback))
  const total = dSum(lines.map((l) => (isValidDecimal(cost(l.id, l.est_unit_cost)) ? dMul(l.qty, cost(l.id, l.est_unit_cost)) : '0')))
  const valid = lines.every((l) => isValidDecimal(cost(l.id, l.est_unit_cost))) && Number(total) > 0

  const buy = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPost<SupplyRequest>(`/api/supply-requests/${request.id}/purchase`, {
        paid_from: paidFrom,
        supplier_name: supplier || null,
        payment_reference: reference || null,
        lines: Object.fromEntries(lines.map((l) => [l.id, cost(l.id, l.est_unit_cost)])),
      }),
    onSuccess: (r) => {
      toast.success(`${r.doc_number} recorded`, `${formatMoney(r.total_cost)} charged to Cleaning and office supplies.`)
      onDone()
    },
  })

  return (
    <Modal
      open={open}
      onClose={onClose}
      width={720}
      title={`Record purchase · ${request.doc_number}`}
      footer={<><Button onClick={onClose}>Cancel</Button><Button variant="primary" disabled={!valid || buy.isPending} onClick={() => buy.mutate()}>{buy.isPending ? 'Posting…' : `Post ${formatMoney(total)}`}</Button></>}
    >
      <div className="space-y-3">
        <div className="ui-card overflow-x-auto">
          <table className="ui-table">
            <thead><tr><th>Item</th><th className="text-right">Qty</th><th className="text-right">Actual cost each</th></tr></thead>
            <tbody>
              {lines.map((l) => (
                <tr key={l.id}>
                  <td>{l.item}</td>
                  <td className="text-right tabular">{Number(l.qty)} {l.unit}</td>
                  <td className="text-right"><input inputMode="decimal" className="ui-input h-8 w-28 tabular text-right" value={cost(l.id, l.est_unit_cost)} onChange={(e) => setCosts({ ...costs, [l.id]: e.target.value.replace(/[^\d.]/g, '') })} /></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <Field label="Paid from" required><Select value={paidFrom} onChange={(e) => setPaidFrom(e.target.value)}>{PAID_FROM.map((p) => (<option key={p.value} value={p.value}>{p.label}</option>))}</Select></Field>
          <Field label="Bought from"><Input placeholder="Shop or supplier" value={supplier} onChange={(e) => setSupplier(e.target.value)} /></Field>
          <Field label="Receipt / reference"><Input value={reference} onChange={(e) => setReference(e.target.value)} /></Field>
        </div>
        <p className="text-xs text-slate-500">Posts Dr Cleaning and office supplies / Cr {titleCase(paidFrom)}. Nothing is added to stock.</p>
        {buy.isError && <InlineError error={buy.error} />}
      </div>
    </Modal>
  )
}
