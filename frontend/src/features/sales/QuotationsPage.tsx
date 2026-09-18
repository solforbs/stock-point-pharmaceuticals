import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { CustomerPicker } from '../../components/CustomerPicker'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { MoneyCell, QtyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, Field, Input, Select, Textarea } from '../../components/ui/primitives'
import { apiGet, apiPost, newIdempotencyKey, withIdempotency } from '../../lib/api'
import { addDaysIso, formatDate, formatDateTime, titleCase } from '../../lib/format'
import { useStores } from '../../lib/hooks'
import { usePermission } from '../../lib/permissions'
import { toast } from '../../lib/toast'
import type { Customer, Paginated, Quotation, SalesOrder } from '../../lib/types'
import { DocLinesEditor, linesPayload, type EditableLine } from './DocLinesEditor'

const STATUSES = ['DRAFT', 'SENT', 'ACCEPTED', 'EXPIRED', 'CONVERTED', 'CANCELLED']

/** Wholesale quotations (V6 21.7): priced by the server; accepting one creates and confirms a sales order. */
export default function QuotationsPage() {
  const [params, setParams] = useSearchParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const canDiscount = usePermission('sale.discount.apply')
  const stores = useStores()
  const [status, setStatus] = useState('')
  const [filterCustomer, setFilterCustomer] = useState<Customer | null>(null)
  const [page, setPage] = useState(1)
  const [creating, setCreating] = useState(false)
  const [customer, setCustomer] = useState<Customer | null>(null)
  const [storeId, setStoreId] = useState('')
  const [validUntil, setValidUntil] = useState(addDaysIso(7))
  const [notes, setNotes] = useState('')
  const [headerDiscount, setHeaderDiscount] = useState('')
  const [lines, setLines] = useState<EditableLine[]>([])
  const selectedId = params.get('quotation')
  const effectiveStore = storeId || stores.data?.find((s) => s.is_sellable)?.id || ''

  const list = useQuery({
    queryKey: ['quotations', 'list', status, filterCustomer?.id, page],
    queryFn: () => apiGet<Paginated<Quotation>>('/api/quotations', { status, customer_id: filterCustomer?.id, page, per_page: 50 }),
    placeholderData: (prev) => prev,
  })

  const create = useMutation({
    meta: { silent: true },
    mutationFn: () =>
      apiPost<Quotation>('/api/quotations', {
        customer_id: customer?.id,
        store_id: effectiveStore,
        valid_until: validUntil,
        notes: notes || null,
        header_discount: headerDiscount || null,
        lines: linesPayload(lines),
      }),
    onSuccess: (q) => {
      queryClient.setQueryData(['quotations', q.id], q)
      queryClient.invalidateQueries({ queryKey: ['quotations'] })
      toast.success(`Quotation ${q.doc_number} created`)
      setCreating(false)
      setLines([])
      setParams({ quotation: q.id })
    },
  })

  const columns: Column<Quotation>[] = [
    { key: 'doc', header: 'Document', render: (q) => <span className="font-semibold tabular">{q.doc_number}</span>, sortValue: (q) => q.doc_number },
    { key: 'customer', header: 'Customer', render: (q) => q.customer?.name ?? '—', sortValue: (q) => q.customer?.name ?? '' },
    { key: 'status', header: 'Status', render: (q) => <StatusBadge status={q.status} /> },
    { key: 'lines', header: 'Lines', align: 'right', render: (q) => <span className="tabular">{q.lines_count ?? '—'}</span> },
    { key: 'valid', header: 'Valid until', render: (q) => formatDate(q.valid_until), sortValue: (q) => q.valid_until },
    { key: 'total', header: 'Total', align: 'right', render: (q) => <MoneyCell value={q.grand_total} />, sortValue: (q) => Number(q.grand_total) },
    { key: 'created', header: 'Created', render: (q) => formatDateTime(q.created_at), sortValue: (q) => q.created_at ?? '' },
  ]

  return (
    <Page>
      <PageHeader
        parent="Sell"
        title="Quotations"
        subtitle="Wholesale quotations priced by the server; accepting one creates and confirms a sales order (reserving stock under the credit check)."
        actions={<Button variant="primary" onClick={() => setCreating(true)}>New quotation</Button>}
      />
      <FilterBar>
        <Field label="Status">
          <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
            <option value="">All</option>
            {STATUSES.map((s) => (<option key={s} value={s}>{titleCase(s)}</option>))}
          </Select>
        </Field>
        <Field label="Customer" className="w-72"><CustomerPicker value={filterCustomer} onChange={(c) => { setFilterCustomer(c); setPage(1) }} placeholder="Any customer" /></Field>
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(q) => q.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(q) => setParams({ quotation: q.id })} selectedKey={selectedId} emptyTitle="No quotations" />
        <Pagination page={list.data} onPage={setPage} />
      </div>

      <Drawer open={creating} onClose={() => setCreating(false)} title="New quotation" width={860}>
        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-3">
            <Field label="Customer" required className="col-span-2"><CustomerPicker value={customer} onChange={setCustomer} /></Field>
            <Field label="Store" required>
              <Select value={effectiveStore} onChange={(e) => setStoreId(e.target.value)}>
                {(stores.data ?? []).map((s) => (<option key={s.id} value={s.id}>{s.code} · {s.name}</option>))}
              </Select>
            </Field>
            <Field label="Valid until" required><Input type="date" value={validUntil} onChange={(e) => setValidUntil(e.target.value)} /></Field>
            {canDiscount && <Field label="Header discount (KES)"><Input inputMode="decimal" value={headerDiscount} onChange={(e) => setHeaderDiscount(e.target.value.replace(/[^\d.]/g, ''))} className="tabular" /></Field>}
            <Field label="Notes" className="col-span-2"><Textarea rows={2} value={notes} onChange={(e) => setNotes(e.target.value)} /></Field>
          </div>
          <Field label="Lines" required><DocLinesEditor lines={lines} onChange={setLines} canDiscount={canDiscount} /></Field>
          {create.isError && <InlineError error={create.error} />}
          <div className="flex justify-end gap-2">
            <Button onClick={() => setCreating(false)}>Cancel</Button>
            <Button variant="primary" disabled={!customer || !effectiveStore || lines.length === 0 || create.isPending} onClick={() => create.mutate()}>{create.isPending ? 'Pricing and saving…' : 'Create quotation'}</Button>
          </div>
        </div>
      </Drawer>

      <QuotationDrawer id={selectedId} onClose={() => setParams({})} onAccepted={(order) => navigate(`/sell/sales-orders?order=${order.id}`)} />
    </Page>
  )
}

function QuotationDrawer({ id, onClose, onAccepted }: { id: string | null; onClose: () => void; onAccepted: (order: SalesOrder) => void }) {
  const queryClient = useQueryClient()
  const [attemptKey, setAttemptKey] = useState(() => newIdempotencyKey())
  const quotation = useQuery({ queryKey: ['quotations', id], queryFn: () => apiGet<Quotation>(`/api/quotations/${id}`), enabled: !!id })

  const accept = useMutation({
    meta: { silent: true },
    mutationFn: () => apiPost<SalesOrder>(`/api/quotations/${id}/accept`, {}, withIdempotency(attemptKey)),
    onSuccess: (order) => {
      toast.success(`Sales order ${order.doc_number} confirmed`, 'Stock reserved under the credit check.')
      queryClient.invalidateQueries({ queryKey: ['quotations'] })
      queryClient.invalidateQueries({ queryKey: ['sales-orders'] })
      queryClient.invalidateQueries({ queryKey: ['inventory'] })
      setAttemptKey(newIdempotencyKey())
      onAccepted(order)
    },
  })

  const q = quotation.data
  const acceptable = q?.status === 'DRAFT' || q?.status === 'SENT'
  return (
    <Drawer
      open={!!id}
      onClose={onClose}
      title={q?.doc_number ?? 'Quotation'}
      subtitle={q ? `${q.customer?.name ?? ''} · valid until ${formatDate(q.valid_until)}` : undefined}
      width={760}
      actions={q && acceptable ? <Button variant="success" size="sm" onClick={() => accept.mutate()} disabled={accept.isPending}>{accept.isPending ? 'Accepting…' : 'Accept → sales order'}</Button> : null}
    >
      {quotation.isLoading && <LoadingSkeleton />}
      {quotation.isError && <InlineError error={quotation.error} />}
      {accept.isError && <InlineError error={accept.error} className="mb-3" />}
      {q && (
        <div className="space-y-4">
          <div className="flex items-center gap-2">
            <StatusBadge status={q.status} />
            {q.converted_sales_order_id && <span className="text-[11.5px] text-[var(--text-muted)]">Converted to order {q.converted_sales_order_id.slice(0, 8)}</span>}
          </div>
          <DescriptionList items={[{ label: 'Notes', value: q.notes ?? '—' }, { label: 'Created', value: formatDateTime(q.created_at) }]} />
          <table className="ui-table">
            <thead><tr><th>#</th><th>Product</th><th className="text-right">Qty</th><th className="text-right">Unit price</th><th className="text-right">Discount</th><th className="text-right">Tax</th><th className="text-right">Total</th></tr></thead>
            <tbody>
              {(q.lines ?? []).map((line) => (
                <tr key={line.id}>
                  <td className="tabular">{line.line_number}</td>
                  <td>{line.product?.name ?? line.product_id}</td>
                  <td className="text-right"><QtyCell value={line.qty} /></td>
                  <td className="text-right"><MoneyCell value={line.unit_price} /></td>
                  <td className="text-right"><MoneyCell value={line.discount_amount} /></td>
                  <td className="text-right"><MoneyCell value={line.tax_amount} /></td>
                  <td className="text-right"><MoneyCell value={line.line_total} /></td>
                </tr>
              ))}
            </tbody>
          </table>
          <div className="ml-auto w-72 grid grid-cols-[1fr_auto] gap-y-1 text-[12.5px] tabular">
            <span className="text-[var(--text-muted)]">Subtotal</span><MoneyCell value={q.subtotal} />
            <span className="text-[var(--text-muted)]">Discount</span><MoneyCell value={`-${q.discount_total}`} />
            <span className="text-[var(--text-muted)]">Tax</span><MoneyCell value={q.tax_total} />
            <span className="font-bold">Grand total</span><MoneyCell value={q.grand_total} className="font-bold" />
          </div>
        </div>
      )}
    </Drawer>
  )
}
