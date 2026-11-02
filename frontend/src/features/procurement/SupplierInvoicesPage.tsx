import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { ConfirmDialog } from '../../components/ui/Modal'
import { MoneyCell, QtyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, Field, Input, Select } from '../../components/ui/primitives'
import { api, apiGet, apiPost, apiPut, ensureCsrfCookie } from '../../lib/api'
import { dSub } from '../../lib/decimal'
import { formatDate, formatDateTime, todayIso } from '../../lib/format'
import { usePurchaseOrder, useSuppliers } from '../../lib/hooks'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { MatchResult, Paginated, PurchaseOrder, SupplierInvoice } from '../../lib/types'
import { SupplierInvoiceExcelButton } from './SupplierInvoiceExcelButton'

/** transport_per_unit is the PO line's share of the order's transport. */
type InvLine = { key: string; purchase_order_line_id: string; product_id: string; label: string; po_qty: string; po_price: string; qty: string; unit_price: string; transport_per_unit: number }

/** Part 9.4 — the invoice as the supplier sent it, then the three-way match. Only a match creates a payable. */
export default function SupplierInvoicesPage() {
  const [params, setParams] = useSearchParams()
  const canMatch = usePermission('invoice.match')
  const suppliers = useSuppliers()
  const [matchStatus, setMatchStatus] = useState('')
  const [supplierFilter, setSupplierFilter] = useState('')
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const selectedId = params.get('invoice')

  const list = useQuery({
    queryKey: ['supplier-invoices', 'list', matchStatus, supplierFilter, page],
    queryFn: () => apiGet<Paginated<SupplierInvoice>>('/api/supplier-invoices', { match_status: matchStatus, supplier_id: supplierFilter, page, per_page: 50 }),
    placeholderData: (prev) => prev,
  })

  const columns: Column<SupplierInvoice>[] = [
    { key: 'doc', header: 'Document', render: (i) => <span className="font-semibold tabular">{i.doc_number}</span>, sortValue: (i) => i.doc_number },
    { key: 'inv', header: 'Supplier invoice no.', render: (i) => <span className="tabular">{i.invoice_number}</span> },
    { key: 'supplier', header: 'Supplier', render: (i) => i.supplier?.name ?? '—', sortValue: (i) => i.supplier?.name ?? '' },
    { key: 'date', header: 'Invoice date', render: (i) => formatDate(i.invoice_date), sortValue: (i) => i.invoice_date },
    { key: 'due', header: 'Due', render: (i) => formatDate(i.due_date), sortValue: (i) => i.due_date ?? '' },
    { key: 'match', header: 'Match', render: (i) => <StatusBadge status={i.match_status} /> },
    { key: 'lines', header: 'Lines', align: 'right', render: (i) => <span className="tabular">{i.lines_count ?? '—'}</span> },
    { key: 'total', header: 'Total', align: 'right', render: (i) => <MoneyCell value={i.grand_total} />, sortValue: (i) => Number(i.grand_total) },
    { key: 'download', header: 'Download', align: 'right', render: (i) => <SupplierInvoiceExcelButton id={i.id} docNumber={i.doc_number} /> },
  ]

  return (
    <Page>
      <PageHeader
        parent="Procurement"
        title="Supplier Invoices"
        subtitle="Record the invoice as received, then match it against the PO and GRN. A match creates the payable; an exception does not."
        actions={
          canMatch ? (
            <div id="tour-invoices-record">
              <Button variant="primary" onClick={() => setCreating(true)}>
                Record supplier invoice
              </Button>
            </div>
          ) : null
        }
      />
      <FilterBar>
        <div id="tour-invoices-match-filter" className="flex flex-wrap gap-1">
          {[['', 'All'], ['UNMATCHED', 'Unmatched'], ['EXCEPTION', 'Exceptions'], ['MATCHED', 'Matched']].map(([v, label]) => (
            <Button key={v} size="sm" variant={matchStatus === v ? 'primary' : 'secondary'} onClick={() => { setMatchStatus(v); setPage(1) }}>{label}</Button>
          ))}
        </div>
        <div className="w-full sm:w-60">
          <Field label="Supplier"><Select value={supplierFilter} onChange={(e) => { setSupplierFilter(e.target.value); setPage(1) }}><option value="">All</option>{(suppliers.data?.data ?? []).map((s) => (<option key={s.id} value={s.id}>{s.name}</option>))}</Select></Field>
        </div>
      </FilterBar>
      <div id="tour-invoices-table" className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(i) => i.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(i) => setParams({ invoice: i.id })} selectedKey={selectedId} emptyTitle="No supplier invoices" />
        <Pagination page={list.data} onPage={setPage} />
      </div>
      {creating && <InvoiceFormDrawer open onClose={() => setCreating(false)} onSaved={(i) => setParams({ invoice: i.id })} />}
      <InvoiceDrawer id={selectedId} onClose={() => setParams({})} />
    </Page>
  )
}

/**
 * Records a supplier invoice, or corrects one (editing). The invoice comes
 * to the goods plus the order's own transport: there is no tax box, and
 * the transport is taken from the purchase order, never typed.
 */
function InvoiceFormDrawer({ open, editing, onClose, onSaved }: { open: boolean; editing?: SupplierInvoice | null; onClose: () => void; onSaved: (i: SupplierInvoice) => void }) {
  const queryClient = useQueryClient()
  const suppliers = useSuppliers()
  const firstPoId = editing?.lines?.[0]?.purchase_order_line?.purchase_order_id ?? ''
  const [supplierId, setSupplierId] = useState(editing?.supplier_id ?? '')
  const [poId, setPoId] = useState(firstPoId)
  const [invoiceNumber, setInvoiceNumber] = useState(editing?.invoice_number ?? '')
  const [invoiceDate, setInvoiceDate] = useState(editing?.invoice_date?.slice(0, 10) ?? todayIso())
  const [dueDate, setDueDate] = useState(editing?.due_date?.slice(0, 10) ?? '')
  const [lines, setLines] = useState<InvLine[]>([])

  const pos = useQuery({ queryKey: ['purchase-orders', 'all'], queryFn: () => apiGet<Paginated<PurchaseOrder>>('/api/purchase-orders', { per_page: 200 }), enabled: open })
  const supplierPos = (pos.data?.data ?? []).filter((po) => (!supplierId || po.supplier_id === supplierId) && po.status !== 'DRAFT' && po.status !== 'CANCELLED')
  const po = usePurchaseOrder(poId || null)

  // Every invoice line must reference a PO line: load them from GET /api/purchase-orders/{id}.
  // When correcting, the invoice's own quantities and prices are kept for the lines it already has.
  useEffect(() => {
    const detail = po.data
    if (!detail) return
    if (!supplierId) setSupplierId(detail.supplier_id)
    const billed = new Map((editing && detail.id === firstPoId ? editing.lines ?? [] : []).map((l) => [l.purchase_order_line_id, l]))
    setLines((detail.lines ?? [])
      .filter((l) => billed.size === 0 || billed.has(l.id))
      .map((l) => ({
        key: l.id, purchase_order_line_id: l.id, product_id: l.product_id, label: `${l.product?.name ?? l.product_id.slice(0, 8)} · ${l.uom?.code ?? ''}`,
        po_qty: l.qty_ordered, po_price: l.unit_price,
        qty: String(Number(billed.get(l.id)?.qty ?? l.qty_ordered)), unit_price: String(Number(billed.get(l.id)?.unit_price ?? l.unit_price)),
        transport_per_unit: Number(l.transport_per_unit ?? 0),
      })))
  }, [po.data, supplierId, editing, firstPoId])

  // The invoice is expected to come to the order's value: the goods plus the order's own transport, nothing more.
  const goods = lines.reduce((s, l) => s + Number(l.qty || 0) * Number(l.unit_price || 0), 0)
  const transport = lines.reduce((s, l) => s + Number(l.qty || 0) * l.transport_per_unit, 0)

  const save = useMutation({
    meta: { silent: true },
    mutationFn: () => {
      const body = {
        supplier_id: supplierId,
        invoice_number: invoiceNumber,
        invoice_date: invoiceDate,
        due_date: dueDate || null,
        lines: lines.map((l) => ({ purchase_order_line_id: l.purchase_order_line_id, product_id: l.product_id, qty: l.qty, unit_price: l.unit_price })),
      }
      return editing ? apiPut<SupplierInvoice>(`/api/supplier-invoices/${editing.id}`, body) : apiPost<SupplierInvoice>('/api/supplier-invoices', body)
    },
    onSuccess: (inv) => {
      queryClient.invalidateQueries({ queryKey: ['supplier-invoices'] })
      queryClient.invalidateQueries({ queryKey: ['suppliers'] })
      if (editing) toast.success(`${inv.doc_number} corrected`, editing.match_status === 'MATCHED' ? 'Its payable was reversed. Run the three-way match again.' : 'Run the three-way match to create the payable.')
      else toast.success(`Supplier invoice ${inv.doc_number} recorded`, 'Run the three-way match to create the payable.')
      setLines([])
      setPoId('')
      setInvoiceNumber('')
      onClose()
      onSaved(inv)
    },
  })

  const valid = !!supplierId && !!invoiceNumber && !!invoiceDate && lines.length > 0 && lines.every((l) => l.purchase_order_line_id && Number(l.qty) > 0 && /^\d+(\.\d+)?$/.test(l.unit_price))

  return (
    <Drawer open={open} onClose={onClose} title={editing ? `Edit ${editing.doc_number}` : 'Record supplier invoice'} width={880}>
      <div className="space-y-3">
        {editing?.match_status === 'MATCHED' && (
          <div className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
            This invoice is matched. Saving a correction reverses its payable and journal; run the three-way match again afterwards.
          </div>
        )}
        <div className="grid grid-cols-3 gap-3">
          <Field label="Supplier" required>
            <Select value={supplierId} onChange={(e) => { setSupplierId(e.target.value); setPoId(''); setLines([]) }}><option value="">Choose…</option>{(suppliers.data?.data ?? []).map((s) => (<option key={s.id} value={s.id}>{s.name}</option>))}</Select>
          </Field>
          <Field label="Purchase order" required className="col-span-2">
            <Select value={poId} onChange={(e) => setPoId(e.target.value)}><option value="">Choose…</option>{supplierPos.map((p) => (<option key={p.id} value={p.id}>{p.doc_number} · {p.supplier?.name} · {p.status}</option>))}</Select>
          </Field>
          <Field label="Invoice number" required><Input value={invoiceNumber} onChange={(e) => setInvoiceNumber(e.target.value)} /></Field>
          <Field label="Invoice date" required><Input type="date" value={invoiceDate} onChange={(e) => setInvoiceDate(e.target.value)} /></Field>
          <Field label="Due date"><Input type="date" value={dueDate} onChange={(e) => setDueDate(e.target.value)} /></Field>
        </div>
        {po.isLoading && <LoadingSkeleton rows={3} />}
        {po.isError && <InlineError error={po.error} />}
        {lines.length > 0 ? (
          <table className="ui-table">
            <thead><tr><th>PO line</th><th className="text-right">PO qty</th><th className="text-right">PO price</th><th>Invoiced qty</th><th>Invoiced price</th><th className="text-right">Line total</th><th /></tr></thead>
            <tbody>
              {lines.map((l) => (
                <tr key={l.key}>
                  <td className="text-xs font-medium">{l.label}</td>
                  <td className="text-right"><QtyCell value={l.po_qty} /></td>
                  <td className="text-right"><MoneyCell value={l.po_price} muted /></td>
                  <td><input value={l.qty} onChange={(e) => setLines(lines.map((x) => (x.key === l.key ? { ...x, qty: e.target.value.replace(/[^\d.]/g, '') } : x)))} className="ui-input h-7 w-20 tabular text-right" /></td>
                  <td><input value={l.unit_price} onChange={(e) => setLines(lines.map((x) => (x.key === l.key ? { ...x, unit_price: e.target.value.replace(/[^\d.]/g, '') } : x)))} className={`ui-input h-7 w-24 tabular text-right ${l.unit_price && Number(l.unit_price) !== Number(l.po_price) ? '!border-amber-400' : ''}`} /></td>
                  <td className="text-right"><MoneyCell value={(Number(l.qty || 0) * Number(l.unit_price || 0)).toFixed(4)} muted /></td>
                  <td className="text-right"><Button size="sm" variant="ghost" onClick={() => setLines(lines.filter((x) => x.key !== l.key))} aria-label="Remove"><Trash2 size={13} /></Button></td>
                </tr>
              ))}
            </tbody>
          </table>
        ) : null}
        {lines.length > 0 ? (
          <div className="flex flex-wrap justify-end gap-x-5 gap-y-1 text-sm tabular text-slate-600">
            <span>Goods <b>{goods.toFixed(2)}</b></span>
            <span title="Taken from the purchase order; it is not typed here">Transport (from the PO) <b>{transport.toFixed(2)}</b></span>
            <span>Invoice total <b className="text-slate-900">{(goods + transport).toFixed(2)}</b></span>
          </div>
        ) : (
          <p className="text-xs text-slate-500">Choose the purchase order; its lines load here for you to enter the invoiced quantity and price.</p>
        )}
        {save.isError && <InlineError error={save.error} />}
        <div className="flex justify-end gap-2">
          <Button onClick={onClose}>Cancel</Button>
          <Button variant="primary" disabled={!valid || save.isPending} onClick={() => save.mutate()}>{save.isPending ? 'Saving…' : editing ? 'Save correction' : 'Record invoice'}</Button>
        </div>
      </div>
    </Drawer>
  )
}

function InvoiceDrawer({ id, onClose }: { id: string | null; onClose: () => void }) {
  const queryClient = useQueryClient()
  const canMatch = usePermission('invoice.match')
  const canDelete = usePermission('record.delete')
  const [match, setMatch] = useState<MatchResult | null>(null)
  const [editing, setEditing] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const invoice = useQuery({ queryKey: ['supplier-invoices', id], queryFn: () => apiGet<SupplierInvoice>(`/api/supplier-invoices/${id}`), enabled: !!id })

  const remove = useMutation({
    meta: { silent: true },
    mutationFn: async (reason: string) => {
      await ensureCsrfCookie()
      return (await api.delete<{ doc_number: string }>(`/api/supplier-invoices/${id}`, { data: { reason } })).data
    },
    onSuccess: (r) => {
      toast.success(`${r.doc_number} deleted`, 'Any payable it raised was reversed; the invoice is kept in the audit log.')
      setDeleting(false)
      queryClient.invalidateQueries({ queryKey: ['supplier-invoices'] })
      queryClient.invalidateQueries({ queryKey: ['suppliers'] })
      onClose()
    },
  })

  const runMatch = useMutation({
    mutationFn: () => apiPost<MatchResult>(`/api/supplier-invoices/${id}/match`),
    onSuccess: (result) => {
      setMatch(result)
      queryClient.invalidateQueries({ queryKey: ['supplier-invoices'] })
      queryClient.invalidateQueries({ queryKey: ['suppliers'] })
      if (result.matched) toast.success('Matched', 'The payable has been created.')
      else toast.warning('Match exception', 'Variances beyond tolerance; routed to the exception queue. No payable yet.')
    },
  })

  const i = invoice.data
  return (
    <Drawer open={!!id} onClose={() => { setMatch(null); onClose() }} title={i ? `${i.doc_number} · ${i.invoice_number}` : 'Supplier invoice'} subtitle={i ? `${i.supplier?.name ?? ''} · dated ${formatDate(i.invoice_date)}` : undefined} width={820}
      actions={i ? (
        <div className="flex items-center gap-2">
          <SupplierInvoiceExcelButton id={i.id} docNumber={i.doc_number} />
          {canMatch && <Button size="sm" onClick={() => setEditing(true)}>Edit</Button>}
          {canMatch && canDelete && <Button size="sm" variant="danger" onClick={() => { remove.reset(); setDeleting(true) }}>Delete</Button>}
          {i.match_status !== 'MATCHED' && canMatch && <Button size="sm" variant="primary" disabled={runMatch.isPending} onClick={() => runMatch.mutate()}>{runMatch.isPending ? 'Matching…' : 'Run three-way match'}</Button>}
        </div>
      ) : null}
    >
      {invoice.isLoading && <LoadingSkeleton />}
      {invoice.isError && <InlineError error={invoice.error} />}
      {i && (
        <div className="space-y-4">
          <div className="flex items-center gap-2"><StatusBadge status={i.match_status} />{i.matched_at && <span className="text-xs text-slate-500">matched {formatDateTime(i.matched_at)}</span>}</div>
          {match && (
            <div className={`rounded-xl px-3.5 py-2.5 border text-xs ${match.matched ? 'border-emerald-300 bg-emerald-50/50 text-emerald-800' : 'border-amber-300 bg-amber-50/50 text-amber-800'}`}>
              <div className="font-bold">{match.matched ? 'Matched — payable created' : 'Exception — no payable'}</div>
              {match.failures.length > 0 && <ul className="list-disc pl-4 mt-1 space-y-0.5">{match.failures.map((f, idx) => (<li key={idx}>{typeof f === 'string' ? f : JSON.stringify(f)}</li>))}</ul>}
            </div>
          )}
          <DescriptionList items={[{ label: 'Due', value: formatDate(i.due_date) }]} />
          <table className="ui-table">
            <thead><tr><th>Product</th><th className="text-right">PO qty</th><th className="text-right">PO price</th><th className="text-right">Invoiced qty</th><th className="text-right">Invoiced price</th><th className="text-right">Variance / unit</th><th className="text-right">Line total</th></tr></thead>
            <tbody>
              {(i.lines ?? []).map((l) => {
                const variance = l.purchase_order_line ? dSub(l.unit_price, l.purchase_order_line.unit_price) : null
                return (
                  <tr key={l.id}>
                    <td>{l.product?.name ?? l.product_id.slice(0, 8)}</td>
                    <td className="text-right"><QtyCell value={l.purchase_order_line?.qty_ordered ?? null} /></td>
                    <td className="text-right"><MoneyCell value={l.purchase_order_line?.unit_price ?? null} muted /></td>
                    <td className="text-right"><QtyCell value={l.qty} /></td>
                    <td className="text-right"><MoneyCell value={l.unit_price} /></td>
                    <td className="text-right"><MoneyCell value={variance} className={variance && Number(variance) !== 0 ? 'font-bold' : ''} /></td>
                    <td className="text-right"><MoneyCell value={l.line_total} /></td>
                  </tr>
                )
              })}
            </tbody>
          </table>
          <div className="ml-auto w-72 grid grid-cols-[1fr_auto] gap-y-1 text-sm tabular">
            <span className="text-slate-500">Goods</span><MoneyCell value={i.subtotal} />
            <span className="text-slate-500">Transport (from the PO)</span><MoneyCell value={i.transport_total ?? '0'} />
            {/* Only an invoice recorded before tax was removed can still carry it; Edit takes it off. */}
            {Number(i.tax_total) > 0 && <><span className="text-rose-700">Tax (not allowed: edit to remove)</span><MoneyCell value={i.tax_total} className="text-rose-700" /></>}
            <span className="font-bold">Grand total</span><MoneyCell value={i.grand_total} className="font-bold" />
          </div>
        </div>
      )}
      {editing && i && <InvoiceFormDrawer open editing={i} onClose={() => setEditing(false)} onSaved={() => { setMatch(null); invoice.refetch() }} />}
      <ConfirmDialog
        open={deleting}
        title={i ? `Delete ${i.doc_number}?` : ''}
        message={i?.match_status === 'MATCHED'
          ? 'It is matched: its payable and journal are reversed first, so the books stay balanced. Refused if the supplier has already been paid for it. The full invoice is kept in the audit log.'
          : 'The invoice is removed. Nothing was posted for it, so the books do not change. The full invoice is kept in the audit log.'}
        confirmLabel="Delete invoice"
        danger
        requireReason="Reason for deleting"
        isPending={remove.isPending}
        onCancel={() => setDeleting(false)}
        onConfirm={(reason) => remove.mutate(reason)}
      />
      {remove.isError && <InlineError error={remove.error} />}
    </Drawer>
  )
}
