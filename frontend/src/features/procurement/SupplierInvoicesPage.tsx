import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Trash2 } from 'lucide-react'
import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { MoneyCell, QtyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, Field, Input, Select } from '../../components/ui/primitives'
import { apiGet, apiPost } from '../../lib/api'
import { dSub } from '../../lib/decimal'
import { formatDate, formatDateTime, todayIso } from '../../lib/format'
import { usePurchaseOrder, useSuppliers } from '../../lib/hooks'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { MatchResult, Paginated, PurchaseOrder, SupplierInvoice } from '../../lib/types'

type InvLine = { key: string; purchase_order_line_id: string; product_id: string; label: string; po_qty: string; po_price: string; qty: string; unit_price: string }

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
  ]

  return (
    <Page>
      <PageHeader
        parent="Buy"
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
      <NewInvoiceDrawer open={creating} onClose={() => setCreating(false)} onCreated={(i) => setParams({ invoice: i.id })} />
      <InvoiceDrawer id={selectedId} onClose={() => setParams({})} />
    </Page>
  )
}

function NewInvoiceDrawer({ open, onClose, onCreated }: { open: boolean; onClose: () => void; onCreated: (i: SupplierInvoice) => void }) {
  const queryClient = useQueryClient()
  const suppliers = useSuppliers()
  const [supplierId, setSupplierId] = useState('')
  const [poId, setPoId] = useState('')
  const [invoiceNumber, setInvoiceNumber] = useState('')
  const [invoiceDate, setInvoiceDate] = useState(todayIso())
  const [dueDate, setDueDate] = useState('')
  const [taxTotal, setTaxTotal] = useState('')
  const [lines, setLines] = useState<InvLine[]>([])

  const pos = useQuery({ queryKey: ['purchase-orders', 'all'], queryFn: () => apiGet<Paginated<PurchaseOrder>>('/api/purchase-orders', { per_page: 200 }), enabled: open })
  const supplierPos = (pos.data?.data ?? []).filter((po) => (!supplierId || po.supplier_id === supplierId) && po.status !== 'DRAFT' && po.status !== 'CANCELLED')
  const po = usePurchaseOrder(poId || null)

  // Every invoice line must reference a PO line: load them from GET /api/purchase-orders/{id}.
  useEffect(() => {
    const detail = po.data
    if (!detail) return
    if (!supplierId) setSupplierId(detail.supplier_id)
    setLines((detail.lines ?? []).map((l) => ({ key: l.id, purchase_order_line_id: l.id, product_id: l.product_id, label: `${l.product?.name ?? l.product_id.slice(0, 8)} · ${l.uom?.code ?? ''}`, po_qty: l.qty_ordered, po_price: l.unit_price, qty: String(Number(l.qty_ordered)), unit_price: String(Number(l.unit_price)) })))
  }, [po.data, supplierId])

  const create = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPost<SupplierInvoice>('/api/supplier-invoices', {
        supplier_id: supplierId,
        invoice_number: invoiceNumber,
        invoice_date: invoiceDate,
        due_date: dueDate || null,
        tax_total: taxTotal || null,
        lines: lines.map((l) => ({ purchase_order_line_id: l.purchase_order_line_id, product_id: l.product_id, qty: l.qty, unit_price: l.unit_price })),
      }),
    onSuccess: (inv) => {
      queryClient.invalidateQueries({ queryKey: ['supplier-invoices'] })
      toast.success(`Supplier invoice ${inv.doc_number} recorded`, 'Run the three-way match to create the payable.')
      setLines([])
      setPoId('')
      setInvoiceNumber('')
      onClose()
      onCreated(inv)
    },
  })

  const valid = !!supplierId && !!invoiceNumber && !!invoiceDate && lines.length > 0 && lines.every((l) => l.purchase_order_line_id && Number(l.qty) > 0 && /^\d+(\.\d+)?$/.test(l.unit_price))

  return (
    <Drawer open={open} onClose={onClose} title="Record supplier invoice" width={880}>
      <div className="space-y-3">
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
          <Field label="Tax total (KES)"><Input inputMode="decimal" className="tabular" value={taxTotal} onChange={(e) => setTaxTotal(e.target.value.replace(/[^\d.]/g, ''))} /></Field>
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
        ) : (
          <p className="text-xs text-slate-500">Choose the purchase order; its lines load here for you to enter the invoiced quantity and price.</p>
        )}
        {create.isError && <InlineError error={create.error} />}
        <div className="flex justify-end gap-2">
          <Button onClick={onClose}>Cancel</Button>
          <Button variant="primary" disabled={!valid || create.isPending} onClick={() => create.mutate()}>{create.isPending ? 'Saving…' : 'Record invoice'}</Button>
        </div>
      </div>
    </Drawer>
  )
}

function InvoiceDrawer({ id, onClose }: { id: string | null; onClose: () => void }) {
  const queryClient = useQueryClient()
  const canMatch = usePermission('invoice.match')
  const [match, setMatch] = useState<MatchResult | null>(null)
  const invoice = useQuery({ queryKey: ['supplier-invoices', id], queryFn: () => apiGet<SupplierInvoice>(`/api/supplier-invoices/${id}`), enabled: !!id })

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
      actions={i && i.match_status !== 'MATCHED' && canMatch ? <Button size="sm" variant="primary" disabled={runMatch.isPending} onClick={() => runMatch.mutate()}>{runMatch.isPending ? 'Matching…' : 'Run three-way match'}</Button> : null}
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
            <span className="text-slate-500">Subtotal</span><MoneyCell value={i.subtotal} />
            <span className="text-slate-500">Tax</span><MoneyCell value={i.tax_total} />
            <span className="font-bold">Grand total</span><MoneyCell value={i.grand_total} className="font-bold" />
          </div>
        </div>
      )}
    </Drawer>
  )
}
