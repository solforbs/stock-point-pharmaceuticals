import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { CreditLimitResolver, type CreditResolution } from '../../components/CreditLimitResolver'
import { CustomerPicker } from '../../components/CustomerPicker'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { MoneyCell, QtyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, Field, Input, Select, Textarea } from '../../components/ui/primitives'
import { apiGet, apiPost, getApiError, newIdempotencyKey, withIdempotency } from '../../lib/api'
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

      <Drawer
        open={creating}
        onClose={() => setCreating(false)}
        title="New Wholesale Quotation"
        subtitle="Wholesale quotation priced by customer tiers & credit pre-validation"
        width={880}
        footer={
          <>
            <div className="flex items-center gap-2 min-w-0">
              <span className="inline-flex items-center justify-center h-6 px-2 rounded-md bg-slate-100 text-slate-700 text-xs font-bold tabular">
                {lines.length} {lines.length === 1 ? 'item' : 'items'}
              </span>
              {customer ? (
                <span className="text-xs text-slate-600 truncate hidden sm:inline">
                  Client: <strong className="text-slate-900 font-bold">{customer.name}</strong>
                </span>
              ) : (
                <span className="text-xs font-semibold text-amber-600">
                  Select a customer to proceed
                </span>
              )}
            </div>
            <div className="flex items-center gap-2 shrink-0">
              <Button onClick={() => setCreating(false)} variant="secondary" size="md">
                Cancel
              </Button>
              <Button
                variant="primary"
                size="md"
                disabled={!customer || !effectiveStore || lines.length === 0 || create.isPending}
                onClick={() => create.mutate()}
                className="font-bold shadow-md shadow-blue-600/20"
              >
                {create.isPending ? 'Pricing and saving…' : 'Create quotation →'}
              </Button>
            </div>
          </>
        }
      >
        <div className="space-y-4">
          {/* Card 1: Client & Store Details */}
          <div className="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-2xs space-y-4">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <div className="flex items-center gap-2">
                <div className="h-7 w-7 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-xs">
                  1
                </div>
                <div>
                  <h3 className="text-[13.5px] font-extrabold text-slate-900">Client & Fulfillment</h3>
                  <p className="text-[11.5px] text-slate-400 font-medium">Customer account, dispatch store, and validity window</p>
                </div>
              </div>
              <span className="text-[10.5px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-blue-50 text-blue-700">
                Required
              </span>
            </div>

            <div className="space-y-3.5">
              <Field label="Customer Account" required>
                <CustomerPicker value={customer} onChange={setCustomer} placeholder="Search customer by name, code, or phone number..." />
              </Field>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
                <Field label="Fulfillment Store" required>
                  <Select value={effectiveStore} onChange={(e) => setStoreId(e.target.value)}>
                    {(stores.data ?? []).map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.code} · {s.name}
                      </option>
                    ))}
                  </Select>
                </Field>

                <Field label="Valid Until" required>
                  <Input type="date" value={validUntil} onChange={(e) => setValidUntil(e.target.value)} />
                </Field>
              </div>
            </div>
          </div>

          {/* Card 2: Quotation Items */}
          <div className="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-2xs space-y-3.5">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <div className="flex items-center gap-2">
                <div className="h-7 w-7 rounded-lg bg-indigo-50 text-indigo-600 flex items-center justify-center font-bold text-xs">
                  2
                </div>
                <div>
                  <h3 className="text-[13.5px] font-extrabold text-slate-900">Quotation Line Items</h3>
                  <p className="text-[11.5px] text-slate-400 font-medium">Add products, quantities, UOMs, and negotiated discounts</p>
                </div>
              </div>
              <span className="text-[11px] font-bold tabular px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-700">
                {lines.length} {lines.length === 1 ? 'line' : 'lines'}
              </span>
            </div>

            <DocLinesEditor lines={lines} onChange={setLines} canDiscount={canDiscount} />
          </div>

          {/* Card 3: Terms & Commercial Notes */}
          <div className="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-2xs space-y-3.5">
            <div className="flex items-center gap-2 border-b border-slate-100 pb-3">
              <div className="h-7 w-7 rounded-lg bg-slate-100 text-slate-600 flex items-center justify-center font-bold text-xs">
                3
              </div>
              <div>
                <h3 className="text-[13.5px] font-extrabold text-slate-900">Commercial Notes & Terms</h3>
                <p className="text-[11.5px] text-slate-400 font-medium">Optional special instructions or overall invoice discount</p>
              </div>
            </div>

            <div className="space-y-3.5">
              {canDiscount && (
                <Field label="Overall Order Discount (KES)" hint="Applied across the whole quotation total">
                  <Input
                    inputMode="decimal"
                    placeholder="0.00"
                    value={headerDiscount}
                    onChange={(e) => setHeaderDiscount(e.target.value.replace(/[^\d.]/g, ''))}
                    className="tabular font-bold"
                  />
                </Field>
              )}

              <Field label="Quotation Notes">
                <Textarea
                  rows={2}
                  placeholder="Add delivery terms, reference numbers, or payment notes for this quote..."
                  value={notes}
                  onChange={(e) => setNotes(e.target.value)}
                />
              </Field>
            </div>
          </div>

          {create.isError && <InlineError error={create.error} />}
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
    mutationFn: (resolution: CreditResolution = {}) => apiPost<SalesOrder>(`/api/quotations/${id}/accept`, resolution, withIdempotency(attemptKey)),
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
      actions={q && acceptable ? <Button variant="success" size="sm" onClick={() => accept.mutate({})} disabled={accept.isPending}>{accept.isPending ? 'Accepting…' : 'Accept → sales order'}</Button> : null}
    >
      {quotation.isLoading && <LoadingSkeleton />}
      {quotation.isError && <InlineError error={quotation.error} />}
      {accept.isError && (
        <div className="mb-3 space-y-2">
          <CreditLimitResolver error={accept.error} pending={accept.isPending} onResolve={(r) => accept.mutate(r)} />
          {getApiError(accept.error).code !== 'CREDIT_LIMIT_EXCEEDED' && <InlineError error={accept.error} />}
        </div>
      )}
      {q && (
        <div className="space-y-4">
          <div className="flex items-center gap-2">
            <StatusBadge status={q.status} />
            {q.converted_sales_order_id && <span className="text-[11.5px] text-[var(--text-muted)]">Converted to order {q.converted_sales_order_id.slice(0, 8)}</span>}
          </div>
          <DescriptionList items={[{ label: 'Notes', value: q.notes ?? '—' }, { label: 'Created', value: formatDateTime(q.created_at) }]} />
          <div className="overflow-x-auto rounded-xl border border-slate-200 shadow-2xs">
            <table className="ui-table">
              <thead><tr><th>#</th><th>Product</th><th className="text-right">Qty</th><th className="text-right">Unit price</th><th className="text-right">Discount</th><th className="text-right">Tax</th><th className="text-right">Total</th></tr></thead>
              <tbody>
                {(q.lines ?? []).map((line) => (
                  <tr key={line.id}>
                    <td className="tabular font-medium text-slate-500">{line.line_number}</td>
                    <td className="font-semibold text-slate-900">{line.product?.name ?? line.product_id}</td>
                    <td className="text-right"><QtyCell value={line.qty} /></td>
                    <td className="text-right"><MoneyCell value={line.unit_price} /></td>
                    <td className="text-right"><MoneyCell value={line.discount_amount} /></td>
                    <td className="text-right"><MoneyCell value={line.tax_amount} /></td>
                    <td className="text-right font-bold"><MoneyCell value={line.line_total} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <div className="ml-auto w-full sm:w-80 rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs space-y-2 text-[13px] tabular">
            <div className="flex justify-between text-slate-500 font-medium"><span>Subtotal</span><MoneyCell value={q.subtotal} /></div>
            <div className="flex justify-between text-slate-500 font-medium"><span>Discount</span><MoneyCell value={`-${q.discount_total}`} /></div>
            <div className="flex justify-between text-slate-500 font-medium"><span>Tax</span><MoneyCell value={q.tax_total} /></div>
            <div className="flex justify-between pt-2 border-t border-slate-200 text-slate-900 font-black text-sm">
              <span>Grand Total</span>
              <MoneyCell value={q.grand_total} className="font-black text-base text-blue-600" />
            </div>
          </div>
        </div>
      )}
    </Drawer>
  )
}
