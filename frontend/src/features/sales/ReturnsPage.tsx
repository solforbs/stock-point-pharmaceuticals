import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Package, Plus, RotateCcw, Truck } from 'lucide-react'
import { useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { ConfirmDialog } from '../../components/ui/Modal'
import { MoneyCell, QtyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, DrawerFooter, Field, FormSection, Input, PrimaryAction, Select, Textarea } from '../../components/ui/primitives'
import { UserPicker } from '../../components/UserPicker'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import { apiGet, apiPost } from '../../lib/api'
import { formatDateTime, titleCase } from '../../lib/format'
import { useStores, useSuppliers } from '../../lib/hooks'
import { dMul } from '../../lib/decimal'
import { formatQty } from '../../lib/money'
import { usePermissions } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { BatchRef, CustomerReturn, CustomerReturnLine, Disposition, ItemRef, Paginated, ReturnReason, Sale, SupplierReturn } from '../../lib/types'
import { BatchLinesEditor, batchLinesPayload, batchLinesValid, type BatchLine } from '../inventory/BatchLinesEditor'
import { ReturnBatchCell, ReturnItemCell, ReturnReasonCell } from './ReturnLineParts'
import { CUSTOMER_RETURN_REASONS, RETURN_REASON_LABEL, SUPPLIER_RETURN_REASONS, returnLineExplained } from './returnReasons'

const DISPOSITIONS: Disposition[] = ['QUARANTINE', 'RESALEABLE', 'DESTROY', 'REJECT']

/** Part 11.1 customer returns (dispositioned before re-entering stock) and Part 11.2 supplier returns. */
export default function ReturnsPage() {
  const [params, setParams] = useSearchParams()
  const [tab, setTab] = useState<'customer' | 'supplier'>(params.get('tab') === 'supplier' ? 'supplier' : 'customer')
  return (
    <Page>
      <PageHeader
        parent="Commerce & Stock"
        title="Returns & Reverse Logistics"
        subtitle="Customer returns inspected line by line before restocking; supplier returns reverse receipts and generate debit notes."
      />
      <div id="tour-returns-tabs" className="flex items-center gap-1.5 p-1 bg-slate-100/80 rounded-xl border border-slate-200/60 w-fit mb-4">
        {(['customer', 'supplier'] as const).map((t) => (
          <button
            key={t}
            type="button"
            onClick={() => { setTab(t); setParams({}) }}
            className={`h-8 px-4 rounded-lg text-xs font-bold transition-all ${tab === t ? 'bg-white text-slate-900 shadow-xs border border-slate-200/80' : 'text-slate-600 hover:text-slate-900'}`}
          >
            {t === 'customer' ? 'Customer returns' : 'Supplier returns'}
          </button>
        ))}
      </div>
      {tab === 'customer' ? <CustomerReturns /> : <SupplierReturns />}
    </Page>
  )
}

function CustomerReturns() {
  const [params, setParams] = useSearchParams()
  const perms = usePermissions()
  const [status, setStatus] = useState('')
  const [page, setPage] = useState(1)
  const selectedId = params.get('return')
  const newFromSale = params.get('sale')

  const list = useQuery({
    queryKey: ['customer-returns', 'list', status, page],
    queryFn: () => apiGet<Paginated<CustomerReturn>>('/api/customer-returns', { status, page, per_page: 50 }),
    placeholderData: (prev) => prev,
  })

  const columns: Column<CustomerReturn>[] = [
    { key: 'doc', header: 'Document', render: (r) => <span className="font-semibold tabular">{r.doc_number}</span>, sortValue: (r) => r.doc_number },
    { key: 'sale', header: 'Original sale', render: (r) => <span className="tabular">{r.sale?.doc_number ?? r.sale_id.slice(0, 8)}</span> },
    { key: 'customer', header: 'Customer', render: (r) => r.customer?.name ?? 'Walk-in' },
    { key: 'status', header: 'Status', render: (r) => <StatusBadge status={r.status} /> },
    { key: 'refund', header: 'Refund', render: (r) => titleCase(r.refund_method) || '—' },
    { key: 'lines', header: 'Lines', align: 'right', render: (r) => <span className="tabular">{r.lines_count ?? '—'}</span> },
    { key: 'total', header: 'Credit', align: 'right', render: (r) => <MoneyCell value={r.grand_total} />, sortValue: (r) => Number(r.grand_total) },
    { key: 'created', header: 'Created', render: (r) => formatDateTime(r.created_at), sortValue: (r) => r.created_at ?? '' },
  ]

  return (
    <>
      <div id="tour-returns-filter">
        <FilterBar>
          <Field label="Status">
            <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
              <option value="">All</option>
              {['DRAFT', 'POSTED', 'REJECTED'].map((s) => (<option key={s} value={s}>{titleCase(s)}</option>))}
            </Select>
          </Field>
          <div className="ml-auto text-xs text-slate-500 self-center">Start a return from the sale: Invoices → open the sale → <strong className="font-semibold text-slate-700">Return items</strong>.</div>
        </FilterBar>
      </div>
      <div id="tour-returns-table" className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(r) => r.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(r) => setParams({ return: r.id })} selectedKey={selectedId} emptyTitle="No customer returns" />
        <Pagination page={list.data} onPage={setPage} />
      </div>
      <NewReturnDrawer saleId={newFromSale} canCreate={perms.has('return.create')} onClose={() => setParams({})} onCreated={(r) => setParams({ return: r.id })} />
      <ReturnDrawer id={selectedId} onClose={() => setParams({})} />
    </>
  )
}

type NewLine = {
  sale_line_id: string
  batch_id: string
  product: ItemRef | null
  productId: string
  batch: BatchRef | null
  baseUomCode: string
  soldAs: string
  issued: string
  qty_base: string
  return_reason: ReturnReason | ''
  remarks: string
  disposition: Disposition | ''
  inspection_notes: string
}

/** The header reason: what was typed, or else a summary of the line reasons. */
function summariseReasons(typed: string, lines: { return_reason: ReturnReason | '' | null }[]): string {
  if (typed.trim()) return typed.trim()
  const reasons = [...new Set(lines.map((l) => l.return_reason).filter((r): r is ReturnReason => !!r))]
  return reasons.map((r) => RETURN_REASON_LABEL[r].replace(' (explain in remarks)', '')).join('; ')
}

function NewReturnDrawer({ saleId, canCreate, onClose, onCreated }: { saleId: string | null; canCreate: boolean; onClose: () => void; onCreated: (r: CustomerReturn) => void }) {
  const stores = useStores()
  const perms = usePermissions()
  const sale = useQuery({ queryKey: ['sales', saleId], queryFn: () => apiGet<Sale>(`/api/sales/${saleId}`), enabled: !!saleId })
  const [storeId, setStoreId] = useState('')
  const [reason, setReason] = useState('')
  const [refundMethod, setRefundMethod] = useState('')
  const [refundReference, setRefundReference] = useState('')
  const [lines, setLines] = useState<NewLine[]>([])

  useEffect(() => {
    if (!sale.data) return
    setStoreId(sale.data.store_id)
    setLines(
      (sale.data.lines ?? []).flatMap((l) =>
        (l.batch_allocations ?? []).filter((a) => !a.is_bonus).map((a) => ({
          sale_line_id: l.id,
          batch_id: a.batch_id,
          product: l.product ?? null,
          productId: l.product_id,
          batch: a.batch ?? null,
          baseUomCode: l.product?.base_uom?.code ?? '',
          soldAs: `${formatQty(l.qty)} ${l.uom?.code ?? ''}`.trim(),
          issued: a.qty_base,
          qty_base: '',
          return_reason: '' as const,
          remarks: '',
          disposition: '' as const,
          inspection_notes: '',
        })),
      ),
    )
  }, [sale.data])

  const create = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPost<CustomerReturn>('/api/customer-returns', {
        sale_id: saleId,
        store_id: storeId,
        reason: headerReason,
        refund_method: refundMethod || null,
        refund_reference: refundReference || null,
        lines: lines.filter((l) => Number(l.qty_base) > 0).map((l) => ({
          sale_line_id: l.sale_line_id,
          batch_id: l.batch_id,
          qty_base: l.qty_base,
          return_reason: l.return_reason || null,
          remarks: l.remarks.trim() || null,
          disposition: l.disposition || null,
          inspection_notes: l.inspection_notes || null,
        })),
      }),
    onSuccess: (r) => {
      toast.success(`Return ${r.doc_number} drafted`, 'Inspect each line, then post.')
      onCreated(r)
    },
  })

  const active = lines.filter((l) => Number(l.qty_base) > 0)
  const headerReason = summariseReasons(reason, active)
  const valid =
    !!storeId &&
    headerReason.length >= 5 &&
    active.length > 0 &&
    active.every((l) => Number(l.qty_base) <= Number(l.issued) && returnLineExplained(l.return_reason, l.remarks))
  const patchLine = (i: number, patch: Partial<NewLine>) => setLines(lines.map((x, j) => (j === i ? { ...x, ...patch } : x)))

  return (
    <Drawer
      open={!!saleId}
      onClose={onClose}
      title={sale.data ? `Return items from ${sale.data.doc_number}` : 'Return items'}
      subtitle={sale.data ? `${sale.data.sale_mode} · ${sale.data.customer?.name ?? 'Walk-in'} · ${formatDateTime(sale.data.posted_at)}` : undefined}
      width={1080}
      footer={
        <DrawerFooter
          badge={`${active.length} items to return`}
          onCancel={onClose}
          onSubmit={() => create.mutate()}
          submitLabel="Create return"
          disabled={!valid || !canCreate || create.isPending}
          isPending={create.isPending}
        />
      }
    >
      {sale.isLoading && <LoadingSkeleton />}
      {sale.isError && <InlineError error={sale.error} />}
      {!canCreate && <InlineError error={{ response: { status: 403, data: { message: 'You do not have the return.create permission.' } } }} className="mb-3" />}
      {sale.data && (
        <div className="space-y-4">
          <FormSection
            title="Return Parameters"
            description="Specify the destination inventory store and customer refund method"
            icon={RotateCcw}
            badge="Required"
          >
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
              <Field label="Receiving Store" required>
                <Select value={storeId} onChange={(e) => setStoreId(e.target.value)}>
                  {(stores.data ?? []).map((s) => (<option key={s.id} value={s.id}>{s.code} · {s.name}</option>))}
                </Select>
              </Field>
              <Field label="Refund Method" hint={sale.data.sale_mode === 'WHOLESALE' ? 'Defaults to customer account credit.' : undefined}>
                <Select value={refundMethod} onChange={(e) => setRefundMethod(e.target.value)}>
                  <option value="">Default</option>
                  {['CASH', 'MPESA', 'BANK', 'CUSTOMER_ACCOUNT'].map((m) => (<option key={m} value={m}>{titleCase(m)}</option>))}
                </Select>
              </Field>
              <Field label="Refund Reference">
                <Input value={refundReference} onChange={(e) => setRefundReference(e.target.value)} placeholder="e.g. M-Pesa code" />
              </Field>
              <Field label="Overall reason" className="sm:col-span-3" hint="Optional when each returning line has its own reason: the line reasons are used.">
                <Textarea rows={2} value={reason} onChange={(e) => setReason(e.target.value)} placeholder={headerReason || 'e.g. Consignment damaged in transit'} />
              </Field>
            </div>
          </FormSection>

          <FormSection
            title="Items being returned"
            description="Enter a return quantity (in base units) on each line coming back, and why. Quarantined stock undergoes QC before release."
            icon={Package}
            badge={`${lines.length} lines on the sale`}
          >
            <div className="overflow-x-auto rounded-xl border border-slate-200">
              <table className="ui-table">
                <thead>
                  <tr>
                    <th>Item</th>
                    <th>Batch · expiry</th>
                    <th className="text-right">Sold</th>
                    <th>Return qty</th>
                    <th>Reason &amp; remarks</th>
                    <th>Disposition &amp; inspection</th>
                  </tr>
                </thead>
                <tbody>
                  {lines.map((l, i) => {
                    const returning = Number(l.qty_base) > 0
                    const needsRemarks = returning && !returnLineExplained(l.return_reason, l.remarks)
                    return (
                      <tr key={`${l.sale_line_id}-${l.batch_id}`} className={`align-top ${returning ? 'bg-blue-50/30' : ''}`}>
                        <td className="min-w-50"><ReturnItemCell product={l.product} fallbackId={l.productId} /></td>
                        <td><ReturnBatchCell batch={l.batch} fallbackId={l.batch_id} /></td>
                        <td className="text-right whitespace-nowrap">
                          <QtyCell value={l.issued} /> <span className="text-[11px] text-slate-500">{l.baseUomCode}</span>
                          {l.soldAs && <div className="text-[11px] text-slate-500">as {l.soldAs}</div>}
                        </td>
                        <td>
                          <div className="flex items-center gap-1">
                            <input
                              value={l.qty_base}
                              onChange={(e) => patchLine(i, { qty_base: e.target.value.replace(/[^\d.]/g, '') })}
                              placeholder="0"
                              className={`ui-input h-7 w-20 tabular text-right ${Number(l.qty_base) > Number(l.issued) ? '!border-rose-500' : ''}`}
                            />
                            <span className="text-[11px] text-slate-500">{l.baseUomCode}</span>
                          </div>
                          {Number(l.qty_base) > Number(l.issued) && <div className="text-[11px] text-rose-600 font-medium mt-0.5">More than sold</div>}
                        </td>
                        <td className="min-w-55 space-y-1">
                          <select value={l.return_reason} onChange={(e) => patchLine(i, { return_reason: e.target.value as ReturnReason | '' })} className="ui-input h-7 w-full">
                            <option value="">Reason…</option>
                            {CUSTOMER_RETURN_REASONS.map((r) => (<option key={r} value={r}>{RETURN_REASON_LABEL[r]}</option>))}
                          </select>
                          <input
                            value={l.remarks}
                            onChange={(e) => patchLine(i, { remarks: e.target.value })}
                            maxLength={500}
                            placeholder="Remarks, e.g. seal broken, 3 strips crushed"
                            className={`ui-input h-7 w-full ${needsRemarks ? '!border-rose-500' : ''}`}
                          />
                        </td>
                        <td className="min-w-45 space-y-1">
                          <select value={l.disposition} onChange={(e) => patchLine(i, { disposition: e.target.value as Disposition | '' })} className="ui-input h-7 w-full">
                            <option value="">Quarantine (default)</option>
                            {DISPOSITIONS.map((d) => (<option key={d} value={d} disabled={d === 'RESALEABLE' && !perms.has('quality.release')}>{titleCase(d)}</option>))}
                          </select>
                          <input value={l.inspection_notes} onChange={(e) => patchLine(i, { inspection_notes: e.target.value })} className="ui-input h-7 w-full" placeholder="Inspection notes…" />
                        </td>
                      </tr>
                    )
                  })}
                </tbody>
              </table>
            </div>
          </FormSection>

          {create.isError && <InlineError error={create.error} />}
        </div>
      )}
    </Drawer>
  )
}

function ReturnDrawer({ id, onClose }: { id: string | null; onClose: () => void }) {
  const queryClient = useQueryClient()
  const perms = usePermissions()
  const { data: user } = useCurrentUser()
  const [witness, setWitness] = useState('')
  const [rejecting, setRejecting] = useState(false)
  const [edits, setEdits] = useState<Record<string, { disposition: Disposition; notes: string }>>({})
  const ret = useQuery({ queryKey: ['customer-returns', id], queryFn: () => apiGet<CustomerReturn>(`/api/customer-returns/${id}`), enabled: !!id })

  function refresh(r: CustomerReturn, msg: string) {
    toast.success(`${r.doc_number} ${msg}`)
    queryClient.invalidateQueries({ queryKey: ['customer-returns'] })
    queryClient.invalidateQueries({ queryKey: ['inventory'] })
    queryClient.invalidateQueries({ queryKey: ['customers'] })
  }
  const disposition = useMutation({
    mutationFn: ({ lineId, d, notes }: { lineId: string; d: Disposition; notes: string }) => apiPost<CustomerReturnLine>(`/api/customer-returns/${id}/lines/${lineId}/disposition`, { disposition: d, inspection_notes: notes || null }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['customer-returns', id] }),
  })
  const post = useMutation({ meta: { silent: true }, mutationFn: () => apiPost<CustomerReturn>(`/api/customer-returns/${id}/post`, { witness_user_id: witness ? Number(witness) : null }), onSuccess: (r) => refresh(r, 'posted — credit note issued') })
  const reject = useMutation({ mutationFn: (reason: string) => apiPost<CustomerReturn>(`/api/customer-returns/${id}/reject`, { reason }), onSuccess: (r) => { setRejecting(false); refresh(r, 'rejected') } })

  const r = ret.data
  const draft = r?.status === 'DRAFT' && perms.has('return.post')
  const anyDestroy = (r?.lines ?? []).some((l) => l.disposition === 'DESTROY')
  const showCost = r?.lines?.some((l) => l.unit_cost !== undefined)

  return (
    <Drawer
      open={!!id}
      onClose={onClose}
      title={r?.doc_number ?? 'Customer return'}
      subtitle={r ? `${r.customer?.name ?? 'Walk-in'} · against ${r.sale?.doc_number ?? ''}` : undefined}
      width={1080}
      actions={draft ? <div className="flex gap-2"><Button size="sm" variant="danger" onClick={() => setRejecting(true)}>Reject</Button><Button size="sm" variant="success" disabled={post.isPending || (anyDestroy && !witness)} onClick={() => post.mutate()}>{post.isPending ? 'Posting…' : 'Post return'}</Button></div> : null}
    >
      {ret.isLoading && <LoadingSkeleton />}
      {ret.isError && <InlineError error={ret.error} />}
      {post.isError && <InlineError error={post.error} className="mb-3" />}
      {r && (
        <div className="space-y-4">
          <div className="flex items-center gap-2 flex-wrap">
            <StatusBadge status={r.status} />
            {r.credit_note_number && <span className="text-xs text-slate-600 tabular font-medium">Credit note {r.credit_note_number}</span>}
            {r.etims_status && <StatusBadge status={r.etims_status} label={`eTIMS ${titleCase(r.etims_status)}`} />}
            {r.recall_id && <Link to={`/quality/recalls?recall=${r.recall_id}`} className="text-xs text-blue-600 hover:text-blue-700 underline font-medium">Linked recall</Link>}
          </div>
          <DescriptionList items={[{ label: 'Reason', value: r.reason }, { label: 'Refund', value: `${titleCase(r.refund_method) || '—'}${r.refund_reference ? ` · ${r.refund_reference}` : ''}` }, { label: 'Posted', value: formatDateTime(r.posted_at) }]} />
          <table className="ui-table">
            <thead><tr><th>Item</th><th>Batch · expiry</th><th className="text-right">Qty</th><th>Reason &amp; remarks</th><th className="text-right">Unit price</th><th className="text-right">Total</th>{showCost && <th className="text-right">Cost</th>}<th>Disposition</th><th>Inspection</th>{draft && <th />}</tr></thead>
            <tbody>
              {(r.lines ?? []).map((l) => {
                const e = edits[l.id] ?? { disposition: l.disposition, notes: l.inspection_notes ?? '' }
                return (
                  <tr key={l.id} className="align-top">
                    <td className="min-w-45"><ReturnItemCell product={l.product} fallbackId={l.product_id} /></td>
                    <td><ReturnBatchCell batch={l.batch} fallbackId={l.batch_id} /></td>
                    <td className="text-right whitespace-nowrap"><QtyCell value={l.qty_base} /> <span className="text-[11px] text-slate-500">{l.product?.base_uom?.code}</span></td>
                    <td className="min-w-40"><ReturnReasonCell reason={l.return_reason} remarks={l.remarks} /></td>
                    <td className="text-right"><MoneyCell value={l.unit_price} /></td>
                    <td className="text-right"><MoneyCell value={l.line_total} /></td>
                    {showCost && <td className="text-right"><MoneyCell value={l.line_cost} muted /></td>}
                    <td>
                      {draft ? (
                        <select value={e.disposition} onChange={(ev) => setEdits({ ...edits, [l.id]: { ...e, disposition: ev.target.value as Disposition } })} className="ui-input h-7">
                          {DISPOSITIONS.map((d) => (<option key={d} value={d} disabled={d === 'RESALEABLE' && !perms.has('quality.release')}>{titleCase(d)}</option>))}
                        </select>
                      ) : (
                        <StatusBadge status={l.disposition} tone={l.disposition === 'RESALEABLE' ? 'green' : l.disposition === 'QUARANTINE' ? 'purple' : 'red'} />
                      )}
                    </td>
                    <td>{draft ? <input value={e.notes} onChange={(ev) => setEdits({ ...edits, [l.id]: { ...e, notes: ev.target.value } })} className="ui-input h-7" /> : (l.inspection_notes ?? '—')}</td>
                    {draft && <td className="text-right"><Button size="sm" disabled={disposition.isPending} onClick={() => disposition.mutate({ lineId: l.id, d: e.disposition, notes: e.notes })}>Save</Button></td>}
                  </tr>
                )
              })}
            </tbody>
          </table>
          <div className="ml-auto w-72 grid grid-cols-[1fr_auto] gap-y-1 text-sm tabular">
            <span className="text-slate-500 font-medium">Subtotal</span><MoneyCell value={r.subtotal} />
            <span className="text-slate-500 font-medium">Tax</span><MoneyCell value={r.tax_total} />
            <span className="font-bold text-slate-900 pt-1 border-t border-slate-200">Grand Total</span><MoneyCell value={r.grand_total} className="font-bold text-slate-900 pt-1 border-t border-slate-200" />
          </div>
          {draft && anyDestroy && (
            <Field label="Witness to destruction (required when any line is DESTROY)" required hint="Must be a different user from you." className="max-w-md">
              <UserPicker value={witness ? Number(witness) : null} onChange={(id) => setWitness(id ? String(id) : '')} exclude={[user?.id]} />
            </Field>
          )}
          {draft && <p className="text-xs text-slate-500">Save each line's disposition before posting. Quarantined lines go to a quarantined batch state; resaleable lines return to free stock; destroyed lines are written off through a witnessed waste disposal.</p>}
        </div>
      )}
      <ConfirmDialog open={rejecting} title="Reject return" confirmLabel="Reject" danger requireReason="Reason" isPending={reject.isPending} onCancel={() => setRejecting(false)} onConfirm={(reason) => reject.mutate(reason)} />
    </Drawer>
  )
}

function SupplierReturns() {
  const [params, setParams] = useSearchParams()
  const queryClient = useQueryClient()
  const perms = usePermissions()
  const suppliers = useSuppliers()
  const stores = useStores()
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const [supplierId, setSupplierId] = useState('')
  const [storeId, setStoreId] = useState('')
  const [reason, setReason] = useState('')
  const [lines, setLines] = useState<BatchLine[]>([])
  const selectedId = params.get('supplier_return')
  const supplierHeaderReason = summariseReasons(reason, lines.map((l) => ({ return_reason: l.return_reason ?? '' })))

  const list = useQuery({ queryKey: ['supplier-returns', 'list', page], queryFn: () => apiGet<Paginated<SupplierReturn>>('/api/supplier-returns', { page, per_page: 50 }), placeholderData: (prev) => prev })
  const detail = useQuery({ queryKey: ['supplier-returns', selectedId], queryFn: () => apiGet<SupplierReturn>(`/api/supplier-returns/${selectedId}`), enabled: !!selectedId })

  const create = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<SupplierReturn>('/api/supplier-returns', { supplier_id: supplierId, store_id: storeId, reason: supplierHeaderReason, lines: batchLinesPayload(lines) }),
    onSuccess: (r) => {
      toast.success(`Supplier return ${r.doc_number} posted`, 'Stock reversed and a debit note raised against the supplier.')
      queryClient.invalidateQueries({ queryKey: ['supplier-returns'] })
      queryClient.invalidateQueries({ queryKey: ['inventory'] })
      queryClient.invalidateQueries({ queryKey: ['suppliers'] })
      setCreating(false)
      setLines([])
      setReason('')
      setParams({ tab: 'supplier', supplier_return: r.id })
    },
  })

  const columns: Column<SupplierReturn>[] = [
    { key: 'doc', header: 'Document', render: (r) => <span className="font-semibold tabular">{r.doc_number}</span>, sortValue: (r) => r.doc_number },
    { key: 'supplier', header: 'Supplier', render: (r) => r.supplier?.name ?? '—' },
    { key: 'store', header: 'Store', render: (r) => r.store?.code ?? '—' },
    { key: 'status', header: 'Status', render: (r) => <StatusBadge status={r.status} /> },
    { key: 'lines', header: 'Lines', align: 'right', render: (r) => <span className="tabular">{r.lines_count ?? '—'}</span> },
    { key: 'reason', header: 'Reason', render: (r) => r.reason },
    { key: 'created', header: 'Created', render: (r) => formatDateTime(r.created_at), sortValue: (r) => r.created_at ?? '' },
  ]
  const d = detail.data

  return (
    <>
      <FilterBar>
        {perms.has('supplier.return') && (
          <PrimaryAction icon={Plus} onClick={() => setCreating(true)}>
            New supplier return
          </PrimaryAction>
        )}
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(r) => r.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(r) => setParams({ tab: 'supplier', supplier_return: r.id })} selectedKey={selectedId} emptyTitle="No supplier returns" />
        <Pagination page={list.data} onPage={setPage} />
      </div>

      <Drawer
        open={creating}
        onClose={() => setCreating(false)}
        title="New Supplier Return"
        subtitle="Reverse goods receipt at batch cost and generate supplier debit note"
        width={1040}
        footer={
          <DrawerFooter
            badge={`${lines.length} lines`}
            onCancel={() => setCreating(false)}
            onSubmit={() => create.mutate()}
            submitLabel="Post supplier return"
            variant="danger"
            disabled={!supplierId || !storeId || supplierHeaderReason.length < 3 || !batchLinesValid(lines) || create.isPending}
            isPending={create.isPending}
          />
        }
      >
        <div className="space-y-4">
          <FormSection
            title="Supplier & Source Store"
            description="Select origin store and target supplier for debit note credit"
            icon={Truck}
            badge="Required"
          >
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <Field label="Supplier" required>
                <Select value={supplierId} onChange={(e) => setSupplierId(e.target.value)}>
                  <option value="">Choose supplier…</option>
                  {(suppliers.data?.data ?? []).map((s) => (<option key={s.id} value={s.id}>{s.name}</option>))}
                </Select>
              </Field>
              <Field label="Source Store" required>
                <Select value={storeId} onChange={(e) => { setStoreId(e.target.value); setLines([]) }}>
                  <option value="">Choose store…</option>
                  {(stores.data ?? []).map((s) => (<option key={s.id} value={s.id}>{s.code} · {s.name}</option>))}
                </Select>
              </Field>
              <Field label="Overall reason" className="sm:col-span-2" hint="Optional when each line has its own reason: the line reasons are used.">
                <Textarea rows={2} value={reason} onChange={(e) => setReason(e.target.value)} placeholder={supplierHeaderReason || 'Reason for returning items to the supplier…'} />
              </Field>
            </div>
          </FormSection>

          <FormSection
            title="Items to return"
            description="Each line: the batch going back, the quantity in base units, and why. Posts immediately at the original batch cost."
            icon={Package}
            badge={`${lines.length} lines`}
          >
            <BatchLinesEditor lines={lines} onChange={setLines} storeId={storeId} reasons={SUPPLIER_RETURN_REASONS} />
          </FormSection>

          {create.isError && <InlineError error={create.error} />}
        </div>
      </Drawer>

      <Drawer open={!!selectedId} onClose={() => setParams({ tab: 'supplier' })} title={d?.doc_number ?? 'Supplier return'} subtitle={d ? `${d.supplier?.name ?? ''}${d.store?.code ? ` · from ${d.store.code}` : ''}` : undefined} width={980}>
        {detail.isLoading && <LoadingSkeleton />}
        {detail.isError && <InlineError error={detail.error} />}
        {d && (
          <div className="space-y-4">
            <div className="flex items-center gap-2"><StatusBadge status={d.status} /><span className="text-xs text-slate-600 font-medium">{d.reason}</span></div>
            <table className="ui-table">
              <thead><tr><th>Item</th><th>Batch · expiry</th><th className="text-right">Qty</th><th>Reason &amp; remarks</th><th className="text-right">Unit cost</th><th className="text-right">Value</th></tr></thead>
              <tbody>
                {(d.lines ?? []).map((l) => (
                  <tr key={l.id} className="align-top">
                    <td className="min-w-45"><ReturnItemCell product={l.product} fallbackId={l.product_id} /></td>
                    <td><ReturnBatchCell batch={l.batch} fallbackId={l.batch_id} /></td>
                    <td className="text-right whitespace-nowrap">{formatQty(l.qty_base)} <span className="text-[11px] text-slate-500">{l.product?.base_uom?.code}</span></td>
                    <td className="min-w-40"><ReturnReasonCell reason={l.return_reason} remarks={l.remarks} /></td>
                    <td className="text-right"><MoneyCell value={l.unit_cost} /></td>
                    <td className="text-right"><MoneyCell value={dMul(l.qty_base, l.unit_cost)} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Drawer>
    </>
  )
}
