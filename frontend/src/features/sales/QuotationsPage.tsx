import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { FileText, Pill, Plus, UserSquare2 } from 'lucide-react'
import { useState } from 'react'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { CreditLimitResolver, type CreditResolution } from '../../components/CreditLimitResolver'
import { CustomerPicker } from '../../components/CustomerPicker'
import { PdfDownloadButton } from '../../components/PdfDownloadButton'
import { DataTable, type Column } from '../../components/ui/DataTable'
import { Drawer } from '../../components/ui/Drawer'
import { MoneyCell, QtyCell } from '../../components/ui/MoneyCell'
import { FilterBar, Page, PageHeader } from '../../components/ui/PageHeader'
import { Pagination } from '../../components/ui/Pagination'
import { InlineError, LoadingSkeleton } from '../../components/ui/States'
import { StatusBadge } from '../../components/ui/StatusBadge'
import { Button, DescriptionList, DrawerFooter, Field, FormSection, Input, PrimaryAction, Select, Textarea } from '../../components/ui/primitives'
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
    {
      key: 'actions',
      header: 'PDF',
      align: 'right',
      render: (q) => (
        <span onClick={(e) => e.stopPropagation()}>
          <PdfDownloadButton url={`/api/quotations/${q.id}/pdf`} filename={q.doc_number} label="PDF" />
        </span>
      ),
    },
  ]

  return (
    <Page>
      <PageHeader
        parent="Commerce & Stock"
        title="Wholesale Quotations"
        subtitle="Formal price quotations with customer tier pricing, credit limit validation, and 1-click conversion to sales orders"
        actions={<PrimaryAction icon={Plus} onClick={() => setCreating(true)}>New Quotation</PrimaryAction>}
      />
      <FilterBar>
        <Field label="Status">
          <Select value={status} onChange={(e) => { setStatus(e.target.value); setPage(1) }}>
            <option value="">All Statuses</option>
            {STATUSES.map((s) => (<option key={s} value={s}>{titleCase(s)}</option>))}
          </Select>
        </Field>
        <Field label="Filter by Customer" className="w-full sm:w-80"><CustomerPicker value={filterCustomer} onChange={(c) => { setFilterCustomer(c); setPage(1) }} placeholder="Search customer…" /></Field>
      </FilterBar>
      <div className="ui-card">
        <DataTable columns={columns} rows={list.data?.data} rowKey={(q) => q.id} isLoading={list.isLoading} error={list.error} onRetry={() => list.refetch()} onRowClick={(q) => setParams({ quotation: q.id })} selectedKey={selectedId} emptyTitle="No quotations" />
        <Pagination page={list.data} onPage={setPage} />
      </div>

      <Drawer
        open={creating}
        onClose={() => setCreating(false)}
        title="Create Wholesale Quotation"
        subtitle="Quote institutional client with automated tier discounts and 7-day validity lock"
        width={880}
      >
        <div className="space-y-4">
          {/* Section 1: Client & Store Details */}
          <FormSection
            title="Client & Fulfillment"
            description="Select customer account, dispatch store, and quote validity date"
            icon={UserSquare2}
            badge="Step 1"
          >
            <div className="space-y-3.5">
              <Field label="Customer Account" required hint="Determines price tier (HOSP, PHARM, NGO) and available credit">
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

                <Field label="Valid Until" required hint="Default 7 days validity per market price policy">
                  <Input type="date" value={validUntil} onChange={(e) => setValidUntil(e.target.value)} />
                </Field>
              </div>
            </div>
          </FormSection>

          {/* Section 2: Quotation Items */}
          <FormSection
            title="Quotation Line Items"
            description="Add medicine lines with custom quantities, purchase UOMs, and authorized discounts"
            icon={Pill}
            badge={`${lines.length} items`}
          >
            <DocLinesEditor lines={lines} onChange={setLines} canDiscount={canDiscount} />
          </FormSection>

          {/* Section 3: Terms & Commercial Notes */}
          <FormSection
            title="Commercial Notes & Terms"
            description="Special delivery instructions, tender notes, or overall discount"
            icon={FileText}
          >
            <div className="space-y-3.5">
              {canDiscount && (
                <Field label="Overall Order Discount (KES)" hint="Applied across the entire quotation total">
                  <Input
                    inputMode="decimal"
                    placeholder="0.00"
                    value={headerDiscount}
                    onChange={(e) => setHeaderDiscount(e.target.value.replace(/[^\d.]/g, ''))}
                    className="w-full sm:w-48 tabular font-bold"
                  />
                </Field>
              )}

              <Field label="Delivery / Tender Notes">
                <Textarea
                  placeholder="e.g. Include Certificate of Analysis (CoA) with delivery to Kakuma Mission Hospital…"
                  value={notes}
                  onChange={(e) => setNotes(e.target.value)}
                  rows={2}
                />
              </Field>
            </div>
          </FormSection>

          {create.isError && <InlineError error={create.error} />}

          <DrawerFooter
            onCancel={() => setCreating(false)}
            onSubmit={() => create.mutate()}
            submitLabel="Create Quotation →"
            isSubmitting={create.isPending}
            disabled={!customer || !effectiveStore || lines.length === 0}
          >
            {customer && (
              <span className="text-xs text-slate-600 truncate hidden sm:inline">
                Client: <strong className="text-slate-900 font-bold">{customer.name}</strong> ({lines.length} items)
              </span>
            )}
          </DrawerFooter>
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
      actions={
        q ? (
          <div className="flex items-center gap-2">
            <PdfDownloadButton url={`/api/quotations/${q.id}/pdf`} filename={q.doc_number} label="Download PDF" />
            {acceptable && (
              <Button variant="success" size="sm" onClick={() => accept.mutate({})} disabled={accept.isPending}>
                {accept.isPending ? 'Accepting…' : 'Accept → sales order'}
              </Button>
            )}
          </div>
        ) : null
      }
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
            {q.converted_sales_order_id && <span className="text-xs text-slate-500 font-medium">Converted to order {q.converted_sales_order_id.slice(0, 8)}</span>}
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
          <div className="ml-auto w-full sm:w-80 rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs space-y-2 text-sm tabular">
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
