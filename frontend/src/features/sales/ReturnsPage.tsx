import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
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
import { Button, DescriptionList, Field, Input, Select, Textarea } from '../../components/ui/primitives'
import { UserPicker } from '../../components/UserPicker'
import { useCurrentUser } from '../../hooks/useCurrentUser'
import { apiGet, apiPost } from '../../lib/api'
import { formatDate, formatDateTime, titleCase } from '../../lib/format'
import { useStores, useSuppliers } from '../../lib/hooks'
import { formatQty } from '../../lib/money'
import { usePermissions } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { CustomerReturn, CustomerReturnLine, Disposition, Paginated, Sale, SupplierReturn } from '../../lib/types'
import { BatchLinesEditor, batchLinesPayload, batchLinesValid, type BatchLine } from '../inventory/BatchLinesEditor'

const DISPOSITIONS: Disposition[] = ['QUARANTINE', 'RESALEABLE', 'DESTROY', 'REJECT']

/** Part 11.1 customer returns (dispositioned before re-entering stock) and Part 11.2 supplier returns. */
export default function ReturnsPage() {
  const [params, setParams] = useSearchParams()
  const [tab, setTab] = useState<'customer' | 'supplier'>(params.get('tab') === 'supplier' ? 'supplier' : 'customer')
  return (
    <Page>
      <PageHeader parent="Sell" title="Returns" subtitle="Customer returns are inspected line by line before anything re-enters stock; supplier returns reverse a receipt and raise a debit note." />
      <div className="flex gap-1 mb-3">
        {(['customer', 'supplier'] as const).map((t) => (
          <button key={t} type="button" onClick={() => { setTab(t); setParams({}) }} className={`h-8 px-3 rounded-md text-[12px] font-semibold ${tab === t ? 'bg-[var(--color-navy)] text-white' : 'bg-[var(--surface-2)] text-[var(--text-secondary)]'}`}>
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
      <FilterBar>
        <Field label="Status">
          <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
            <option value="">All</option>
            {['DRAFT', 'POSTED', 'REJECTED'].map((s) => (<option key={s} value={s}>{titleCase(s)}</option>))}
          </Select>
        </Field>
        <div className="ml-auto text-[11.5px] text-[var(--text-muted)] self-center">Start a return from the sale: Invoices → open the sale → <b>Return items</b>.</div>
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(r) => r.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(r) => setParams({ return: r.id })} selectedKey={selectedId} emptyTitle="No customer returns" />
        <Pagination page={list.data} onPage={setPage} />
      </div>
      <NewReturnDrawer saleId={newFromSale} canCreate={perms.has('return.create')} onClose={() => setParams({})} onCreated={(r) => setParams({ return: r.id })} />
      <ReturnDrawer id={selectedId} onClose={() => setParams({})} />
    </>
  )
}

type NewLine = { sale_line_id: string; batch_id: string; label: string; issued: string; qty_base: string; disposition: Disposition | ''; inspection_notes: string }

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
          label: `${l.product?.name ?? l.product_id.slice(0, 8)} · ${a.batch?.batch_number ?? a.batch_id.slice(0, 8)}`,
          issued: a.qty_base,
          qty_base: '',
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
        reason,
        refund_method: refundMethod || null,
        refund_reference: refundReference || null,
        lines: lines.filter((l) => Number(l.qty_base) > 0).map((l) => ({ sale_line_id: l.sale_line_id, batch_id: l.batch_id, qty_base: l.qty_base, disposition: l.disposition || null, inspection_notes: l.inspection_notes || null })),
      }),
    onSuccess: (r) => {
      toast.success(`Return ${r.doc_number} drafted`, 'Inspect each line, then post.')
      onCreated(r)
    },
  })

  const active = lines.filter((l) => Number(l.qty_base) > 0)
  const valid = !!storeId && reason.trim().length >= 5 && active.length > 0 && active.every((l) => Number(l.qty_base) <= Number(l.issued))

  return (
    <Drawer open={!!saleId} onClose={onClose} title={sale.data ? `Return items from ${sale.data.doc_number}` : 'Return items'} subtitle={sale.data ? `${sale.data.sale_mode} · ${sale.data.customer?.name ?? 'Walk-in'} · ${formatDateTime(sale.data.posted_at)}` : undefined} width={860}>
      {sale.isLoading && <LoadingSkeleton />}
      {sale.isError && <InlineError error={sale.error} />}
      {!canCreate && <InlineError error={{ response: { status: 403, data: { message: 'You do not have the return.create permission.' } } }} className="mb-3" />}
      {sale.data && (
        <div className="space-y-4">
          <div className="grid grid-cols-3 gap-3">
            <Field label="Receiving store" required>
              <Select value={storeId} onChange={(e) => setStoreId(e.target.value)}>{(stores.data ?? []).map((s) => (<option key={s.id} value={s.id}>{s.code} · {s.name}</option>))}</Select>
            </Field>
            <Field label="Refund method" hint={sale.data.sale_mode === 'WHOLESALE' ? 'Defaults to the customer account (credit note).' : undefined}>
              <Select value={refundMethod} onChange={(e) => setRefundMethod(e.target.value)}>
                <option value="">Default</option>
                {['CASH', 'MPESA', 'BANK', 'CUSTOMER_ACCOUNT'].map((m) => (<option key={m} value={m}>{titleCase(m)}</option>))}
              </Select>
            </Field>
            <Field label="Refund reference"><Input value={refundReference} onChange={(e) => setRefundReference(e.target.value)} /></Field>
            <Field label="Reason" required className="col-span-3"><Textarea rows={2} value={reason} onChange={(e) => setReason(e.target.value)} placeholder="At least 5 characters" /></Field>
          </div>
          <Field label="Lines (from the sale's batch allocations)" required hint="Quantities in base units, never more than was issued from that batch. Disposition defaults to quarantine; RESALEABLE needs quality.release.">
            <table className="ui-table">
              <thead><tr><th>Product · batch</th><th className="text-right">Issued</th><th>Return qty</th><th>Disposition</th><th>Inspection notes</th></tr></thead>
              <tbody>
                {lines.map((l, i) => (
                  <tr key={`${l.sale_line_id}-${l.batch_id}`}>
                    <td className="tabular text-[12px]">{l.label}</td>
                    <td className="text-right"><QtyCell value={l.issued} /></td>
                    <td><input value={l.qty_base} onChange={(e) => setLines(lines.map((x, j) => (j === i ? { ...x, qty_base: e.target.value.replace(/[^\d.]/g, '') } : x)))} className={`ui-input h-7 w-24 tabular text-right ${Number(l.qty_base) > Number(l.issued) ? '!border-[var(--status-red)]' : ''}`} /></td>
                    <td>
                      <select value={l.disposition} onChange={(e) => setLines(lines.map((x, j) => (j === i ? { ...x, disposition: e.target.value as Disposition | '' } : x)))} className="ui-input h-7">
                        <option value="">Quarantine (default)</option>
                        {DISPOSITIONS.map((d) => (<option key={d} value={d} disabled={d === 'RESALEABLE' && !perms.has('quality.release')}>{titleCase(d)}</option>))}
                      </select>
                    </td>
                    <td><input value={l.inspection_notes} onChange={(e) => setLines(lines.map((x, j) => (j === i ? { ...x, inspection_notes: e.target.value } : x)))} className="ui-input h-7" /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </Field>
          {create.isError && <InlineError error={create.error} />}
          <div className="flex justify-end gap-2">
            <Button onClick={onClose}>Cancel</Button>
            <Button variant="primary" disabled={!valid || !canCreate || create.isPending} onClick={() => create.mutate()}>{create.isPending ? 'Saving…' : 'Create return'}</Button>
          </div>
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
      width={860}
      actions={draft ? <div className="flex gap-2"><Button size="sm" variant="danger" onClick={() => setRejecting(true)}>Reject</Button><Button size="sm" variant="success" disabled={post.isPending || (anyDestroy && !witness)} onClick={() => post.mutate()}>{post.isPending ? 'Posting…' : 'Post return'}</Button></div> : null}
    >
      {ret.isLoading && <LoadingSkeleton />}
      {ret.isError && <InlineError error={ret.error} />}
      {post.isError && <InlineError error={post.error} className="mb-3" />}
      {r && (
        <div className="space-y-4">
          <div className="flex items-center gap-2 flex-wrap">
            <StatusBadge status={r.status} />
            {r.credit_note_number && <span className="text-[11.5px] tabular">Credit note {r.credit_note_number}</span>}
            {r.etims_status && <StatusBadge status={r.etims_status} label={`eTIMS ${titleCase(r.etims_status)}`} />}
            {r.recall_id && <Link to={`/quality/recalls?recall=${r.recall_id}`} className="text-[11.5px] text-[var(--color-navy)] underline">Linked recall</Link>}
          </div>
          <DescriptionList items={[{ label: 'Reason', value: r.reason }, { label: 'Refund', value: `${titleCase(r.refund_method) || '—'}${r.refund_reference ? ` · ${r.refund_reference}` : ''}` }, { label: 'Posted', value: formatDateTime(r.posted_at) }]} />
          <table className="ui-table">
            <thead><tr><th>Product</th><th>Batch</th><th className="text-right">Qty</th><th className="text-right">Unit price</th><th className="text-right">Total</th>{showCost && <th className="text-right">Cost</th>}<th>Disposition</th><th>Inspection</th>{draft && <th />}</tr></thead>
            <tbody>
              {(r.lines ?? []).map((l) => {
                const e = edits[l.id] ?? { disposition: l.disposition, notes: l.inspection_notes ?? '' }
                return (
                  <tr key={l.id}>
                    <td>{l.product?.name ?? l.product_id.slice(0, 8)}</td>
                    <td className="tabular">{l.batch?.batch_number ?? l.batch_id.slice(0, 8)}{l.batch && <div className="text-[10.5px] text-[var(--text-muted)]">exp {formatDate(l.batch.expiry_date)}</div>}</td>
                    <td className="text-right"><QtyCell value={l.qty_base} /></td>
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
          <div className="ml-auto w-72 grid grid-cols-[1fr_auto] gap-y-1 text-[12.5px] tabular">
            <span className="text-[var(--text-muted)]">Subtotal</span><MoneyCell value={r.subtotal} />
            <span className="text-[var(--text-muted)]">Tax</span><MoneyCell value={r.tax_total} />
            <span className="font-bold">Credit total</span><MoneyCell value={r.grand_total} className="font-bold" />
          </div>
          {draft && anyDestroy && (
            <Field label="Witness to destruction (required when any line is DESTROY)" required hint="Must be a different user from you." className="max-w-md">
              <UserPicker value={witness ? Number(witness) : null} onChange={(id) => setWitness(id ? String(id) : '')} exclude={[user?.id]} />
            </Field>
          )}
          {draft && <p className="text-[11px] text-[var(--text-muted)]">Save each line's disposition before posting. Quarantined lines go to a quarantined batch state; resaleable lines return to free stock; destroyed lines are written off through a witnessed waste disposal.</p>}
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

  const list = useQuery({ queryKey: ['supplier-returns', 'list', page], queryFn: () => apiGet<Paginated<SupplierReturn>>('/api/supplier-returns', { page, per_page: 50 }), placeholderData: (prev) => prev })
  const detail = useQuery({ queryKey: ['supplier-returns', selectedId], queryFn: () => apiGet<SupplierReturn>(`/api/supplier-returns/${selectedId}`), enabled: !!selectedId })

  const create = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<SupplierReturn>('/api/supplier-returns', { supplier_id: supplierId, store_id: storeId, reason, lines: batchLinesPayload(lines) }),
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
        {perms.has('supplier.return') && <Button variant="primary" onClick={() => setCreating(true)}>New supplier return</Button>}
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(r) => r.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(r) => setParams({ tab: 'supplier', supplier_return: r.id })} selectedKey={selectedId} emptyTitle="No supplier returns" />
        <Pagination page={list.data} onPage={setPage} />
      </div>

      <Drawer open={creating} onClose={() => setCreating(false)} title="New supplier return" width={820}>
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-3">
            <Field label="Supplier" required>
              <Select value={supplierId} onChange={(e) => setSupplierId(e.target.value)}><option value="">Choose…</option>{(suppliers.data?.data ?? []).map((s) => (<option key={s.id} value={s.id}>{s.name}</option>))}</Select>
            </Field>
            <Field label="Store" required>
              <Select value={storeId} onChange={(e) => { setStoreId(e.target.value); setLines([]) }}><option value="">Choose…</option>{(stores.data ?? []).map((s) => (<option key={s.id} value={s.id}>{s.code} · {s.name}</option>))}</Select>
            </Field>
            <Field label="Reason" required className="col-span-2"><Textarea rows={2} value={reason} onChange={(e) => setReason(e.target.value)} /></Field>
          </div>
          <Field label="Lines" required hint="Posts immediately: the reverse of a goods receipt at the batch's cost.">
            <BatchLinesEditor lines={lines} onChange={setLines} storeId={storeId} />
          </Field>
          {create.isError && <InlineError error={create.error} />}
          <div className="flex justify-end gap-2">
            <Button onClick={() => setCreating(false)}>Cancel</Button>
            <Button variant="danger" disabled={!supplierId || !storeId || reason.trim().length < 3 || !batchLinesValid(lines) || create.isPending} onClick={() => create.mutate()}>{create.isPending ? 'Posting…' : 'Post supplier return'}</Button>
          </div>
        </div>
      </Drawer>

      <Drawer open={!!selectedId} onClose={() => setParams({ tab: 'supplier' })} title={d?.doc_number ?? 'Supplier return'} subtitle={d?.supplier?.name} width={720}>
        {detail.isLoading && <LoadingSkeleton />}
        {detail.isError && <InlineError error={detail.error} />}
        {d && (
          <div className="space-y-4">
            <div className="flex items-center gap-2"><StatusBadge status={d.status} /><span className="text-[12px]">{d.reason}</span></div>
            <table className="ui-table">
              <thead><tr><th>Product</th><th>Batch</th><th className="text-right">Qty (base)</th><th className="text-right">Unit cost</th></tr></thead>
              <tbody>
                {(d.lines ?? []).map((l) => (
                  <tr key={l.id}><td>{l.product?.name ?? l.product_id.slice(0, 8)}</td><td className="tabular">{l.batch?.batch_number ?? l.batch_id.slice(0, 8)}</td><td className="text-right">{formatQty(l.qty_base)}</td><td className="text-right"><MoneyCell value={l.unit_cost} /></td></tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Drawer>
    </>
  )
}
